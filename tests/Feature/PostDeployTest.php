<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostDeployTest extends TestCase
{
    use RefreshDatabase;

    private string $flag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flag = storage_path('app/deploy-pending');
        @unlink($this->flag);
        @unlink($this->flag.'.running');
    }

    protected function tearDown(): void
    {
        @unlink($this->flag);
        @unlink($this->flag.'.running');
        parent::tearDown();
    }

    public function test_does_nothing_without_a_pending_deploy(): void
    {
        $this->artisan('sparingin:post-deploy')->assertSuccessful()->doesntExpectOutputToContain('done');
    }

    public function test_runs_once_for_a_pending_deploy(): void
    {
        file_put_contents($this->flag, 'abc1234');

        $this->artisan('sparingin:post-deploy')->assertSuccessful()->expectsOutputToContain('Post-deploy abc1234 done.');
        $this->assertFileDoesNotExist($this->flag);
        $this->assertFileDoesNotExist($this->flag.'.running');

        // Next cron tick: nothing left to do.
        $this->artisan('sparingin:post-deploy')->assertSuccessful()->doesntExpectOutputToContain('done');
    }
}
