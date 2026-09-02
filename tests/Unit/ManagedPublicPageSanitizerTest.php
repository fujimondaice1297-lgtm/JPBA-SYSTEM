<?php

namespace Tests\Unit;

use App\Services\ManagedPublicPageSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManagedPublicPageSanitizerTest extends TestCase
{
    #[Test]
    public function it_keeps_safe_internal_images_and_removes_executable_image_attributes(): void
    {
        $html = '<img src="/images/jpba/instructor/Sticker_A.jpg" alt="A級" onerror="alert(1)">'
            .'<img src="javascript:alert(1)" alt="危険">';

        $sanitized = app(ManagedPublicPageSanitizer::class)->sanitize($html);

        $this->assertStringContainsString('src="/images/jpba/instructor/Sticker_A.jpg"', $sanitized);
        $this->assertStringContainsString('alt="A級"', $sanitized);
        $this->assertStringNotContainsString('onerror', $sanitized);
        $this->assertStringNotContainsString('javascript:', $sanitized);
    }
}
