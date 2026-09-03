<?php

namespace Tests\Unit;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationStyleTest extends TestCase
{
    public function test_default_pagination_uses_bootstrap_five_views(): void
    {
        $paginator = new LengthAwarePaginator(
            items: range(1, 10),
            total: 30,
            perPage: 10,
            currentPage: 1,
            options: ['path' => '/items'],
        );

        $html = $paginator->links()->render();

        $this->assertStringContainsString('<ul class="pagination">', $html);
        $this->assertStringContainsString('page-item', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }
}
