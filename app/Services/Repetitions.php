<?php

namespace App\Services;

use App\Data\Card\CardSet as CardSetData;
use App\Data\Repetition\QueueCard;
use App\Data\Repetition\RepetitionQueue;
use App\Data\Repetition\RepetitionSet;
use App\Enums\ReviewState;
use App\Models\Card\Card;
use App\Models\Card\CardReviewLog;
use App\Models\Card\CardReviewState;
use App\Models\Card\CardSet;
use App\Models\Card\CardSetRepetition;
use App\Models\User;
use App\Support\ContentAccess;
use App\Support\LocalDay;
use App\Support\RepetitionLimits;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * «Мои повторения» и очередь «На сегодня». Набор участвует, пока он в повторениях пользователя,
 * не удалён и виден ему (свой или каталог); удалённые карточки не участвуют. Прогресс по карточкам
 * (card_review_states) от этого не зависит и возвращается вместе с набором.
 *
 * К сроку: review — due до конца текущего локального дня (LocalDay), learning / relearning —
 * due не позже чем через LEARN_AHEAD_SECONDS, чтобы шаги 1 и 10 мин проходили внутри сессии.
 */
class Repetitions
{
    /** Шаги изучения, до которых осталось не больше 20 мин, показываются сейчас — в конце очереди. */
    public const LEARN_AHEAD_SECONDS = 1200;

    public const DEFAULT_BATCH = 50;

    public const MAX_BATCH = 100;

    private const SHORT_STEPS = [ReviewState::learning->value, ReviewState::relearning->value];

    public function __construct(
        private readonly CardReviews $reviews,
        private readonly RepetitionLimits $limits,
    ) {}

    /**
     * Наборы в повторениях пользователя, которые сейчас участвуют (видимые, не удалённые).
     *
     * @return Builder<CardSet>
     */
    public function sets(User $user): Builder
    {
        $query = CardSet::query()->whereIn('id', self::membership($user)->select('card_set_id'));
        ContentAccess::applyVisibleScopeFor($query, $user);

        return $query;
    }

    public function contains(User $user, CardSet $set): bool
    {
        return $this->sets($user)->whereKey($set->id)->exists();
    }

    /** Лимит наборов (RepetitionLimits) считается по участвующим наборам. */
    public function limitReached(User $user): bool
    {
        $max = $this->limits->maxSetsInRepetition($user);

        return $max !== null && $this->sets($user)->count() >= $max;
    }

    /** Добавить набор; повторное добавление ничего не меняет. Видимость и лимит проверяет вызывающий код. */
    public function add(User $user, CardSet $set): CardSetRepetition
    {
        return CardSetRepetition::query()->createOrFirst(['user_id' => $user->id, 'card_set_id' => $set->id]);
    }

    /** Убрать набор из повторений; прогресс по карточкам остаётся. */
    public function remove(User $user, CardSet $set): void
    {
        self::membership($user)->where('card_set_id', $set->id)->delete();
    }

    /** @return Collection<int, RepetitionSet> в порядке добавления */
    public function overview(User $user, ?CardSet $only = null, ?DateTimeInterface $now = null): Collection
    {
        $now = self::moment($now);
        $day = LocalDay::current($user->settings->effectiveTimezone(), $now);

        $sets = $this->sets($user)
            ->when($only, fn (Builder $query) => $query->whereKey($only->id))
            ->with(['section', 'user'])
            ->get()
            ->keyBy('id');
        $ids = $sets->keys()->all();

        $cards = self::countBySet(Card::query()->whereIn('cards.card_set_id', $ids));
        $started = self::countBySet($this->started($user, $ids));
        $due = self::countBySet(self::whereDue($this->started($user, $ids), $now, $day));

        return self::membership($user)->whereIn('card_set_id', $ids)->orderBy('id')->get()
            ->map(fn (CardSetRepetition $membership) => RepetitionSet::from([
                'card_set' => CardSetData::from($sets[$membership->card_set_id]),
                'added_at' => $membership->created_at->toJSON(),
                'cards_count' => $cards[$membership->card_set_id] ?? 0,
                'due_count' => $due[$membership->card_set_id] ?? 0,
                'new_count' => ($cards[$membership->card_set_id] ?? 0) - ($started[$membership->card_set_id] ?? 0),
            ]));
    }

    /**
     * Очередь «На сегодня» по всем наборам в повторениях или по одному ($only — вызывающий код
     * проверяет, что он в повторениях). Порядок: просроченные шаги изучения → повторения к сроку →
     * новые в пределах дневного лимита → шаги изучения на ближайшие LEARN_AHEAD_SECONDS.
     */
    public function queue(User $user, ?CardSet $only = null, int $limit = self::DEFAULT_BATCH, ?DateTimeInterface $now = null): RepetitionQueue
    {
        $now = self::moment($now);
        $day = LocalDay::current($user->settings->effectiveTimezone(), $now);
        $ahead = $now->addSeconds(self::LEARN_AHEAD_SECONDS);

        $setIds = $this->sets($user)->when($only, fn (Builder $query) => $query->whereKey($only->id))->select('id');

        $shortNow = $this->started($user, $setIds)
            ->whereIn('card_review_states.state', self::SHORT_STEPS)
            ->where('card_review_states.due', '<=', $now);
        $reviews = $this->started($user, $setIds)
            ->where('card_review_states.state', ReviewState::review->value)
            ->where('card_review_states.due', '<', $day->end);
        $shortAhead = $this->started($user, $setIds)
            ->whereIn('card_review_states.state', self::SHORT_STEPS)
            ->where('card_review_states.due', '>', $now)
            ->where('card_review_states.due', '<=', $ahead);

        $newLimit = $this->limits->newCardsPerDay($user);
        $newStarted = CardReviewLog::query()
            ->where('user_id', $user->id)
            ->where('state_before', ReviewState::new->value)
            ->where('reviewed_at', '>=', $day->start)
            ->where('reviewed_at', '<', $day->end)
            ->count();
        $fresh = $this->fresh($user, $setIds);
        $newCount = min(max(0, $newLimit - $newStarted), (clone $fresh)->count());

        $batch = collect();
        $take = function (Builder $query, int $max) use (&$batch, $limit): void {
            $max = min($max, $limit - $batch->count());
            if ($max > 0) {
                $batch = $batch->concat($query->limit($max)->get());
            }
        };

        $byDue = fn (Builder $query) => $query->with('card')->orderBy('card_review_states.due')->orderBy('card_review_states.id');
        $take($byDue(clone $shortNow), $limit);
        $take($byDue(clone $reviews), $limit);
        $take($fresh->orderBy('r.id')->orderBy('cards.id'), $newCount);
        $take($byDue(clone $shortAhead), $limit);

        return RepetitionQueue::from([
            'day' => $day->date,
            'day_ends_at' => $day->end->toJSON(),
            'due_count' => $shortNow->count() + $reviews->count() + $shortAhead->count(),
            'new_count' => $newCount,
            'new_limit' => $newLimit,
            'new_started_today' => $newStarted,
            'next_due_at' => self::json($this->started($user, $setIds)
                ->whereIn('card_review_states.state', self::SHORT_STEPS)
                ->where('card_review_states.due', '>', $ahead)
                ->min('card_review_states.due')),
            'cards' => $batch->map(fn (Card|CardReviewState $item) => $this->item($user, $item, $now))->all(),
        ]);
    }

    private function item(User $user, Card|CardReviewState $item, CarbonImmutable $now): QueueCard
    {
        [$card, $state] = $item instanceof Card
            ? [$item, new CardReviewState(['user_id' => $user->id, 'card_id' => $item->id])]
            : [$item->card, $item];

        return QueueCard::fromState($card, $state, $this->reviews->preview($user, $card, $now, $state));
    }

    /**
     * Уже начатые карточки пользователя в наборах $setIds (удалённые карточки — нет).
     *
     * @param  list<int>|Builder<CardSet>  $setIds
     * @return Builder<CardReviewState>
     */
    private function started(User $user, array|Builder $setIds): Builder
    {
        return CardReviewState::query()
            ->select('card_review_states.*')
            ->join('cards', 'cards.id', '=', 'card_review_states.card_id')
            ->whereNull('cards.deleted_at')
            ->whereIn('cards.card_set_id', $setIds)
            ->where('card_review_states.user_id', $user->id)
            ->where('card_review_states.state', '!=', ReviewState::new->value);
    }

    /**
     * Ещё не начатые карточки; r — строка «Моих повторений» (порядок наборов).
     *
     * @param  Builder<CardSet>  $setIds
     * @return Builder<Card>
     */
    private function fresh(User $user, Builder $setIds): Builder
    {
        return Card::query()
            ->select('cards.*')
            ->join('card_set_repetitions as r', fn ($join) => $join->on('r.card_set_id', '=', 'cards.card_set_id')->where('r.user_id', $user->id))
            ->whereIn('cards.card_set_id', $setIds)
            ->whereNotExists(fn (QueryBuilder $state) => $state->selectRaw('1')
                ->from('card_review_states')
                ->whereColumn('card_review_states.card_id', 'cards.id')
                ->where('card_review_states.user_id', $user->id)
                ->where('card_review_states.state', '!=', ReviewState::new->value));
    }

    /** @param  Builder<CardReviewState>  $query */
    private static function whereDue(Builder $query, CarbonImmutable $now, LocalDay $day): Builder
    {
        return $query->where(fn (Builder $due) => $due
            ->where(fn (Builder $review) => $review
                ->where('card_review_states.state', ReviewState::review->value)
                ->where('card_review_states.due', '<', $day->end))
            ->orWhere(fn (Builder $short) => $short
                ->whereIn('card_review_states.state', self::SHORT_STEPS)
                ->where('card_review_states.due', '<=', $now->addSeconds(self::LEARN_AHEAD_SECONDS))));
    }

    /** @return Collection<int, int> card_set_id => число строк */
    private static function countBySet(Builder $query): Collection
    {
        return $query->toBase()
            ->select('cards.card_set_id', DB::raw('count(*) as aggregate'))
            ->groupBy('cards.card_set_id')
            ->pluck('aggregate', 'card_set_id')
            ->map(fn ($count) => (int) $count);
    }

    /** @return Builder<CardSetRepetition> */
    private static function membership(User $user): Builder
    {
        return CardSetRepetition::query()->where('user_id', $user->id);
    }

    /** Как в CardReviews: в БД время с точностью до секунды. */
    private static function moment(?DateTimeInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now())->startOfSecond();
    }

    private static function json(?string $moment): ?string
    {
        return $moment === null ? null : CarbonImmutable::parse($moment, 'UTC')->toJSON();
    }
}
