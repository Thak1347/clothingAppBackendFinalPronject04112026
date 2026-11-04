<?php
namespace App\Jobs;

use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
    ) {
    }

    public function handle(PushNotificationService $svc): void
    {
        $svc->sendToUser($this->userId, $this->title, $this->body);
    }
}
