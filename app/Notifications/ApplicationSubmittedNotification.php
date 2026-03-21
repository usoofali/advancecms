<?php

namespace App\Notifications;

use App\Models\Applicant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Applicant $applicant)
    {
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
        return (new MailMessage)
            ->subject('['.config('app.name').'] Admission Application Received - '.$this->applicant->application_number)
            ->greeting('Hello '.$this->applicant->full_name.',')
            ->line('Your application for admission has been received successfully.')
            ->line('Application Reference: **'.$this->applicant->application_number.'**')
            ->line('Program: **'.$this->applicant->program->name.'**')
            ->line('Institution: **'.$this->applicant->institution->name.'**')
            ->action('Access Applicant Dashboard', route('applicant.portal', $this->applicant->application_number))
            ->line('Please keep your application reference safe as you will need it to track your status and complete your registration.')
            ->line('Thank you for choosing us!')
            ->salutation('Best regards, '.config('app.name').' Admissions');
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
