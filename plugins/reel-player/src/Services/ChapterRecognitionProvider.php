<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/**
 * Extension point for an explicitly configured recognition service.
 * No provider is bundled, enabled, or contacted by the chapter detector.
 * Implementations must enforce their own sample/upload limits, credentials,
 * opt-in, timeout and privacy policy. null means no reliable match.
 */
interface ChapterRecognitionProvider
{
    /** @return array{title:string, artist:string}|null */
    public function identify(string $authorizedLocalPath, float $start, float $sampleSeconds): ?array;
}
