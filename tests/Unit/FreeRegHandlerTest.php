<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use Matchmaker\Core\FreeRegHandler;
use Matchmaker\Repository\MatchRepository;

class FreeRegHandlerTest
{
    private FreeRegHandler $handler;

    public function setUp(): void
    {
        $GLOBALS['__mm_options']  = [];
        $GLOBALS['__mm_usermeta'] = [];
        $GLOBALS['wpdb']->queries = [];
        $this->handler = FreeRegHandler::instance();
    }

    public function test_form_id_matching_and_custom_options(): void
    {
        // Default ID
        if (!$this->handler->matches_form_id('2784843')) {
            throw new \RuntimeException("Expected default form ID 2784843 to match");
        }

        // Multiple comma-separated IDs
        update_option('mm_free_reg_form_id', '101, 202, 303');
        if (!$this->handler->matches_form_id('202') || !$this->handler->matches_form_id('303')) {
            throw new \RuntimeException("Expected comma-separated form IDs to match");
        }
        if ($this->handler->matches_form_id('999')) {
            throw new \RuntimeException("Form ID 999 should not match");
        }
    }
}
