<?php

namespace Tests\Unit;

use Domain\SiteRegistry\Site;
use Domain\SiteRegistry\SiteState;
use PHPUnit\Framework\TestCase;

class SiteStateTest extends TestCase
{
    public function test_all_five_states_exist(): void
    {
        $this->assertSame('wp-full', SiteState::WP_FULL->value);
        $this->assertSame('wp-degraded', SiteState::WP_DEGRADED->value);
        $this->assertSame('non-wp', SiteState::NON_WP->value);
        $this->assertSame('down', SiteState::DOWN->value);
        $this->assertSame('unknown', SiteState::UNKNOWN->value);
    }

    public function test_try_from_unknown_string_returns_null(): void
    {
        $this->assertNull(SiteState::tryFrom('invalid'));
    }

    public function test_site_basic_auth_null_when_no_token(): void
    {
        $site = new Site(1, 'http://example.com', 'example', 'auto', 'admin', null, 0, SiteState::WP_FULL);
        $this->assertNull($site->basicAuth());
    }

    public function test_site_basic_auth_strips_spaces(): void
    {
        $site = new Site(1, 'http://example.com', 'example', 'auto', 'admin', 'my token', 0, SiteState::WP_FULL);
        $this->assertSame('admin:mytoken', $site->basicAuth());
    }

    public function test_site_basic_auth_defaults_user_to_admin(): void
    {
        $site = new Site(1, 'http://example.com', 'example', 'auto', null, 'token', 0, SiteState::WP_FULL);
        $this->assertSame('admin:token', $site->basicAuth());
    }
}
