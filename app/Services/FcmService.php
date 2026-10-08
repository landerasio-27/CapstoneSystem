<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Throwable;

/**
 * Wrapper around the Firebase Admin SDK's messaging component.
 *
 * Disabled by default (FCM_ENABLED=false in .env) until a real Firebase project's
 * service-account JSON is placed at storage/app/firebase/service-account.json.
 * Every method fails soft (logs + returns false) so the rest of the app keeps
 * working even without real credentials — only the push itself is skipped.
 */
class FcmService
{
    /** Topic every inspection-staff device subscribes to for announcements. */
    public const ANNOUNCEMENTS_TOPIC = 'announcements';

    /** Android notification channels (must match the Flutter app's channels). */
    public const CHANNEL_ANNOUNCEMENTS = 'announcements';
    public const CHANNEL_INSPECTIONS = 'inspections';
    public const CHANNEL_URGENT = 'urgent_inspections';

    public function isEnabled(): bool
    {
        return (bool) config('services.fcm.enabled')
            && is_string(config('firebase.projects.app.credentials'))
            && file_exists($this->credentialsPath());
    }

    private function credentialsPath(): string
    {
        $path = (string) config('firebase.projects.app.credentials', '');

        // Support a project-relative path (e.g. storage/app/firebase/service-account.json).
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)
            ? $path
            : base_path($path);
    }

    private function messaging(): Messaging
    {
        return app('firebase.messaging');
    }

    /**
     * High-importance Android config so heads-up notifications appear on the
     * dedicated "announcements" channel even when the app is backgrounded.
     */
    private function androidConfig(string $channel = self::CHANNEL_ANNOUNCEMENTS): AndroidConfig
    {
        return AndroidConfig::fromArray([
            'priority' => 'high',
            'notification' => [
                'channel_id' => $channel,
                'sound' => 'default',
            ],
        ]);
    }

    /**
     * Send a notification to every device subscribed to a topic (preferred path).
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = [], string $channel = self::CHANNEL_ANNOUNCEMENTS): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $message = CloudMessage::new()
                ->toTopic($topic)
                ->withNotification(FirebaseNotification::create($title, $body))
                ->withData($this->stringifyData($data))
                ->withAndroidConfig($this->androidConfig($channel));

            $this->messaging()->send($message);

            return true;
        } catch (Throwable $e) {
            Log::warning('FCM topic send failed: '.$e->getMessage(), ['topic' => $topic]);

            return false;
        }
    }

    /**
     * Send to explicit device tokens and prune any the API reports as invalid/expired.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = [], string $channel = self::CHANNEL_ANNOUNCEMENTS): bool
    {
        $tokens = array_values(array_filter(array_unique($tokens)));

        if (! $this->isEnabled() || $tokens === []) {
            return false;
        }

        try {
            $message = CloudMessage::new()
                ->withNotification(FirebaseNotification::create($title, $body))
                ->withData($this->stringifyData($data))
                ->withAndroidConfig($this->androidConfig($channel));

            $report = $this->messaging()->sendMulticast($message, $tokens);

            $invalid = array_merge($report->invalidTokens(), $report->unknownTokens());
            if ($invalid !== []) {
                DeviceToken::whereIn('token', $invalid)->delete();
                Log::info('Pruned invalid FCM tokens', ['count' => count($invalid)]);
            }

            return $report->successes()->count() > 0;
        } catch (Throwable $e) {
            Log::warning('FCM multicast send failed: '.$e->getMessage());

            return false;
        }
    }

    public function subscribeToTopic(string $topic, array $tokens): void
    {
        $tokens = array_values(array_filter($tokens));

        if (! $this->isEnabled() || $tokens === []) {
            return;
        }

        try {
            $this->messaging()->subscribeToTopic($topic, $tokens);
        } catch (Throwable $e) {
            Log::warning('FCM topic subscribe failed: '.$e->getMessage(), ['topic' => $topic]);
        }
    }

    public function unsubscribeFromTopic(string $topic, array $tokens): void
    {
        $tokens = array_values(array_filter($tokens));

        if (! $this->isEnabled() || $tokens === []) {
            return;
        }

        try {
            $this->messaging()->unsubscribeFromTopic($topic, $tokens);
        } catch (Throwable $e) {
            Log::warning('FCM topic unsubscribe failed: '.$e->getMessage(), ['topic' => $topic]);
        }
    }

    /**
     * Backwards-compatible single-user push (uses the user's legacy fcm_token column
     * plus any registered device tokens).
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        $tokens = $user->deviceTokens()->pluck('token')->all();

        if ($user->fcm_token) {
            $tokens[] = $user->fcm_token;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    private function stringifyData(array $data): array
    {
        return array_map(static fn ($value) => (string) $value, $data);
    }
}
