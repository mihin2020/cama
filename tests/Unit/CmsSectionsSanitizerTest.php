<?php

namespace Tests\Unit;

use App\Support\CmsSectionsSanitizer;
use PHPUnit\Framework\TestCase;

class CmsSectionsSanitizerTest extends TestCase
{
    private function page(array $widget, array $settings = []): array
    {
        return [['settings' => $settings, 'columns' => [['settings' => [], 'widgets' => [$widget]]]]];
    }

    private function widget(array $sections): array
    {
        return $sections[0]['columns'][0]['widgets'][0];
    }

    public function test_script_is_removed_from_rich_text_but_layout_is_kept(): void
    {
        $widget = $this->widget(CmsSectionsSanitizer::sanitize($this->page([
            'type' => 'elementor_text_editor',
            'content' => ['html' => '<div class="grid" style="color:red"><p onclick="alert(1)">Bonjour <strong>CAMA</strong></p><script>alert(2)</script><img src="x" onerror="alert(3)"></div>'],
        ])));

        $html = $widget['content']['html'];
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('<div class="grid" style="color:red">', $html);
        $this->assertStringContainsString('<strong>CAMA</strong>', $html);
    }

    public function test_heading_title_and_tag_cannot_inject_markup(): void
    {
        $widget = $this->widget(CmsSectionsSanitizer::sanitize($this->page([
            'type' => 'elementor_heading',
            'content' => ['title' => 'Titre <img src=x onerror=alert(1)>', 'tag' => 'script', 'link' => ['url' => 'javascript:alert(1)']],
        ])));

        $this->assertSame('h2', $widget['content']['tag']);
        $this->assertStringNotContainsString('onerror', $widget['content']['title']);
        $this->assertSame('#', $widget['content']['link']['url']);
    }

    public function test_links_with_executable_schemes_are_neutralised(): void
    {
        foreach (['javascript:alert(1)', ' JaVa script:alert(1)', 'data:text/html,<script>', 'vbscript:x'] as $href) {
            $widget = $this->widget(CmsSectionsSanitizer::sanitize($this->page(['type' => 'button', 'content' => ['href' => $href]])));
            $this->assertSame('#', $widget['content']['href'], $href);
        }

        foreach (['https://cama.bf', '/contact', '#faq', 'mailto:info@cama.bf', 'tel:+22600000000'] as $href) {
            $widget = $this->widget(CmsSectionsSanitizer::sanitize($this->page(['type' => 'button', 'content' => ['href' => $href]])));
            $this->assertSame($href, $widget['content']['href']);
        }
    }

    public function test_style_values_cannot_break_out_of_the_style_attribute(): void
    {
        $sections = CmsSectionsSanitizer::sanitize($this->page(
            ['type' => 'elementor_button', 'content' => [], 'style' => ['textColor' => 'red" onmouseover="alert(1)', 'bgImage' => "x');}</style><script>"]],
            ['bgImage' => 'javascript:alert(1)'],
        ));
        $style = $this->widget($sections)['style'];

        $this->assertStringNotContainsString('"', $style['textColor']);
        $this->assertStringNotContainsString('<', $style['bgImage']);
        $this->assertStringNotContainsString("'", $style['bgImage']);
        $this->assertSame('', $sections[0]['settings']['bgImage']);
    }

    public function test_free_html_widget_is_left_untouched(): void
    {
        $html = '<iframe src="https://www.google.com/maps/embed"></iframe>';
        $widget = $this->widget(CmsSectionsSanitizer::sanitize($this->page(['type' => 'html', 'content' => ['html' => $html]])));

        $this->assertSame($html, $widget['content']['html']);
    }
}
