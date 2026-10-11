<?php

namespace App\Widgets;

final class WidgetContext
{
    private array $memo = [];

    public function __construct(public readonly string $page, public readonly array $data = [], public readonly string $presentation = 'responsive', public readonly array $instance = []) {}

    /** Instance metadata is separate; expensive page loaders remain shared. */
    public function forInstance(array $instance): self
    {
        $context = new self($this->page, $this->data, $this->presentation, $instance);
        $context->memo =& $this->memo;
        return $context;
    }

    public function once(string $key, callable $loader): mixed
    {
        if (!array_key_exists($key, $this->memo)) $this->memo[$key] = $loader();
        return $this->memo[$key];
    }

    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }

    public function posts(): \App\Models\Post
    {
        return $this->once('post.model', static fn() => new \App\Models\Post());
    }

    public function categories(): array
    {
        return $this->once('categories', fn() => $this->get('categories') ?? $this->posts()->getCategories());
    }

    public function translate(string $indexKey, string $showKey): string
    {
        $prefix = trim((string)$this->get('posts_translation_prefix', 'posts')) ?: 'posts';
        return return_translation($prefix . '_' . ($this->page === 'posts.show' ? $showKey : $indexKey));
    }

    public function partial(string $name, array $data): string
    {
        // Themes can supply presentation-specific partials without duplicating data loaders.
        if ($this->presentation !== 'responsive' && in_array($this->presentation, ['desktop', 'mobile'], true)) {
            $path = theme()->resolveFile('partials', 'widgets/' . $name . '.' . $this->presentation);
            if ($path !== null) return theme()->partial('widgets/' . $name . '.' . $this->presentation, $data + ['context' => $this]);
        }
        return theme()->partial('widgets/' . $name, $data + ['context' => $this]);
    }
}
