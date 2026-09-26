<?php

namespace App\Notifications;

use App\Enums\TeacherVerificationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Письмо учителю о решении по заявке. Уходит через очередь приложения
 * (соединение и очередь по умолчанию — их слушает воркер docker-compose).
 */
class TeacherVerificationDecided extends Notification implements ShouldQueue
{
    use Queueable;

    // Решение и комментарий фиксируются на момент решения: к отправке заявка могла измениться
    public function __construct(
        public TeacherVerificationStatus $decision,
        public ?string $reviewerComment = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $text] = match ($this->decision) {
            TeacherVerificationStatus::approved => [
                'Вы — проверенный учитель FUISIC',
                'Ваша заявка на статус «Проверенный учитель» одобрена. Теперь вы можете предлагать свои материалы в общий каталог.',
            ],
            TeacherVerificationStatus::rejected => [
                'Заявка на статус «Проверенный учитель» отклонена',
                'К сожалению, вашу заявку на статус «Проверенный учитель» отклонили. Вы можете исправить данные и подать заявку повторно.',
            ],
            TeacherVerificationStatus::revoked => [
                'Статус «Проверенный учитель» отозван',
                'Ваш статус «Проверенный учитель» отозван, публикация материалов в общий каталог больше недоступна. Вы можете подать новую заявку.',
            ],
            TeacherVerificationStatus::pending => [
                'Заявка на статус «Проверенный учитель»',
                'Ваша заявка на статус «Проверенный учитель» на рассмотрении.',
            ],
        };

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Здравствуйте, '.$notifiable->name.'!')
            ->line($text);

        if (filled($this->reviewerComment)) {
            $mail->line('Комментарий проверяющего: '.$this->reviewerComment);
        }

        $frontend = rtrim((string) config('fuisic-auth.frontend_url'), '/');
        if ($frontend !== '') {
            $mail->action('Открыть FUISIC', $frontend);
        }

        return $mail->salutation('Команда FUISIC');
    }
}
