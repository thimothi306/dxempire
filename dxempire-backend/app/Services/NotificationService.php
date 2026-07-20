<?php

namespace App\Services;

use App\Integrations\Notifications\ExpoNotificationService;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(private ExpoNotificationService $expo) {}

    /**
     * Persist an in-app notification and push it to all of the user's devices.
     */
    public function notify(
        User $user,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): Notification {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);

        $stringData = array_map('strval', array_merge($data, ['type' => $type]));

        $user->loadMissing('pushTokens');

        foreach ($user->pushTokens as $pt) {
            try {
                $this->expo->send($pt->token, $title, $body, $stringData);
            } catch (\Throwable $e) {
                Log::warning("Push failed for user {$user->id}: " . $e->getMessage());
            }
        }

        return $notification;
    }

    /**
     * Persist and push to many users at once. Uses batch push for efficiency.
     *
     * @param Collection<int, User> $users Must be loaded with pushTokens relation.
     */
    public function notifyMany(
        Collection $users,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): void {
        $messages = [];

        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type'    => $type,
                'title'   => $title,
                'body'    => $body,
                'data'    => $data,
            ]);

            $stringData = array_map('strval', array_merge($data, ['type' => $type]));

            foreach ($user->pushTokens as $pt) {
                $messages[] = [
                    'to'    => $pt->token,
                    'title' => $title,
                    'body'  => $body,
                    'sound' => 'default',
                    'data'  => $stringData,
                ];
            }
        }

        if (!empty($messages)) {
            $this->expo->sendBatch($messages);
        }
    }
}
