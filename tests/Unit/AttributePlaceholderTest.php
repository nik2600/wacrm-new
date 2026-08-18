<?php

namespace Tests\Unit;

use App\Http\Controllers\AttributesController;
use PHPUnit\Framework\TestCase;

class AttributePlaceholderTest extends TestCase
{
    public function test_empty_body_is_valid(): void
    {
        $this->assertNull(AttributesController::validatePlaceholders(''));
        $this->assertNull(AttributesController::validatePlaceholders(null));
        $this->assertNull(AttributesController::validatePlaceholders('plain text no placeholders'));
    }

    public function test_contiguous_one_based_passes(): void
    {
        $this->assertNull(AttributesController::validatePlaceholders('Hi {{1}}'));
        $this->assertNull(AttributesController::validatePlaceholders('Hi {{1}}, your code is {{2}}.'));
        $this->assertNull(AttributesController::validatePlaceholders('{{1}} {{2}} {{3}}'));
    }

    public function test_duplicate_index_passes_when_all_indices_form_contiguous_set(): void
    {
        // Body has {{1}} twice and {{2}} once — unique set is {1,2} which is contiguous.
        $this->assertNull(AttributesController::validatePlaceholders('Hi {{1}}, code {{2}}, again {{1}}'));
    }

    public function test_gap_is_rejected(): void
    {
        $this->assertNotNull(AttributesController::validatePlaceholders('Hi {{1}}, code {{3}}'));
        $this->assertNotNull(AttributesController::validatePlaceholders('{{2}}'));        // missing 1
        $this->assertNotNull(AttributesController::validatePlaceholders('{{1}} {{4}}'));  // missing 2,3
    }

    public function test_zero_index_is_rejected(): void
    {
        $this->assertNotNull(AttributesController::validatePlaceholders('Hi {{0}}'));
    }

    public function test_whitespace_inside_braces_is_tolerated(): void
    {
        $this->assertNull(AttributesController::validatePlaceholders('Hi {{ 1 }} you'));
        $this->assertNull(AttributesController::validatePlaceholders('{{1}}{{ 2 }}{{3}}'));
    }
}
