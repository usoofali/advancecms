<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdmissionDecisionNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public $applicant,
        public $status,
        public ?string $reason = null
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $portalUrl = route('applicant.portal', ['application_number' => $this->applicant->application_number]);

        if ($this->status === 'admitted') {
            $letterUrl = route('applicant.admission-letter', ['applicant' => $this->applicant->application_number]);

            return (new MailMessage)
                ->subject('Congratulations! Admission Offered')
                ->greeting('Dear '.$this->applicant->full_name.',')
                ->line('We are pleased to inform you that you have been offered provisional admission into '.$this->applicant->institution->name.' for the '.$this->applicant->program->name.' program.')
                ->line('Your journey with us begins now. To formally accept this offer and complete your enrollment, please follow the steps below:')
                ->line('1. Log in to your application portal.')
                ->line('2. Pay the required admission and registration fees (minimum 50% for enrollment).')
                ->line('3. Complete your academic credential profile.')
                ->line('You can view and print your provisional admission letter directly via the link below:')
                ->line($letterUrl)
                ->action('Access Application Portal', $portalUrl)
                ->line('Failure to complete these steps within the stipulated time may lead to the forfeiture of this admission offer.')
                ->line('Once again, congratulations!');
        }

        $mailMessage = (new MailMessage)
            ->subject('Admission Decision Notification')
            ->greeting('Dear '.$this->applicant->full_name.',')
            ->line('Thank you for your interest in '.$this->applicant->institution->name.'.')
            ->line('We regret to inform you that after a careful review of your application for the '.$this->applicant->program->name.' program, we are unable to offer you admission at this time.');

        if ($this->reason) {
            $mailMessage->line('Reason for decision: '.$this->reason);
        }

        return $mailMessage
            ->line('We appreciate the time and effort you put into your application and wish you the best in your future academic endeavors.')
            ->action('View Application Status', $portalUrl);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
