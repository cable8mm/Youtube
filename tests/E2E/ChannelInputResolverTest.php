<?php

namespace Cable8mm\Youtube\Tests\E2E;

use Cable8mm\Youtube\Sync\ChannelInputResolver;

class ChannelInputResolverTest extends TestCase
{
    public function test_channel_id_resolves_to_that_channel(): void
    {
        $channel = (new ChannelInputResolver)->resolve('UCk1SpWNzOs4MYmr0uICEntg');

        $this->assertSame('UCk1SpWNzOs4MYmr0uICEntg', $channel->id);
        $this->assertObjectHasProperty('snippet', $channel);
        $this->assertNotEmpty($channel->contentDetails->relatedPlaylists->uploads);
    }

    public function test_handle_and_urls_resolve_to_google(): void
    {
        $resolver = new ChannelInputResolver;

        foreach (['@Google', 'https://www.youtube.com/@Google', 'https://www.youtube.com/user/Google'] as $input) {
            $this->assertSame('UCK8sQmJBp8GCxrOtXWBpyEA', $resolver->resolve($input)->id, $input);
        }
    }

    public function test_channel_id_without_a_channel_is_not_found(): void
    {
        $this->assertSame(ChannelInputResolver::NOT_FOUND, (new ChannelInputResolver)->resolve('UC0000000000000000000000'));
    }
}
