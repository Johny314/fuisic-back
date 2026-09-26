<?php

namespace App\Policies\Card;

use App\Models\Card\Card;
use App\Models\Card\CardSet;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Карточка наследует права своего набора.
 */
class CardPolicy
{
    public function __construct(private readonly CardSetPolicy $sets) {}

    public function view(?User $user, Card $card): Response
    {
        return $this->sets->view($user, $card->cardSet);
    }

    public function create(User $user, CardSet $set): bool
    {
        return $this->sets->update($user, $set);
    }

    public function update(User $user, Card $card): bool
    {
        return $this->sets->update($user, $card->cardSet);
    }

    public function delete(User $user, Card $card): bool
    {
        return $this->sets->update($user, $card->cardSet);
    }
}
