<?php

namespace Cable8mm\Youtube\Tests\Sync;

use Cable8mm\Youtube\Http\ApiClient;
use Cable8mm\Youtube\Sync\ChannelInputResolver;
use Cable8mm\Youtube\Tests\Resources\FakeApiClient;
use Cable8mm\Youtube\Tests\Resources\FakeYoutube;
use Cable8mm\Youtube\Tests\TestCase;
use Cable8mm\Youtube\Youtube;
use PHPUnit\Framework\Attributes\DataProvider;

class ChannelInputResolverTest extends TestCase
{
    private FakeApiClient $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeApiClient;
        $this->app->instance(Youtube::class, new FakeYoutube($this->fake));
    }

    private function queueChannel(string $id): void
    {
        $this->fake->responses[] = json_encode(['kind' => 'youtube#channelListResponse', 'etag' => 'e', 'items' => [['id' => $id]]]);
    }

    public static function resolvableInputs(): array
    {
        return [
            'Channel ID' => ['UCk1SpWNzOs4MYmr0uICEntg', 'id', 'UCk1SpWNzOs4MYmr0uICEntg'],
            'Handle' => ['@Google', 'forHandle', '@Google'],
            '/channel/ URL' => ['https://www.youtube.com/channel/UCk1SpWNzOs4MYmr0uICEntg', 'id', 'UCk1SpWNzOs4MYmr0uICEntg'],
            '/channel/ sub-page URL' => ['https://www.youtube.com/channel/UCk1SpWNzOs4MYmr0uICEntg/videos', 'id', 'UCk1SpWNzOs4MYmr0uICEntg'],
            '/@ URL' => ['https://www.youtube.com/@Google', 'forHandle', '@Google'],
            '/@ sub-page URL' => ['https://m.youtube.com/@Google/videos?view=0', 'forHandle', '@Google'],
            '/user/ URL' => ['https://www.youtube.com/user/Google', 'forUsername', 'Google'],
            'padded cell' => ['  @Google  ', 'forHandle', '@Google'],
        ];
    }

    #[DataProvider('resolvableInputs')]
    public function test_each_input_form_sends_one_channels_request(string $input, string $param, string $value): void
    {
        $this->queueChannel('UC1');

        $channel = (new ChannelInputResolver)->resolve($input);

        $this->assertSame('UC1', $channel->id);
        $this->assertCount(1, $this->fake->requests);
        $this->assertSame(ApiClient::ENDPOINTS['channels.list'], $this->fake->requests[0]['url']);
        $this->assertSame($value, $this->fake->requests[0]['params'][$param]);
        $this->assertSame('id,snippet,contentDetails', $this->fake->requests[0]['params']['part']);
    }

    public static function unsupportedInputs(): array
    {
        return [
            '/c/ URL' => ['https://www.youtube.com/c/Google'],
            'search term' => ['hello world'],
            'empty cell' => [''],
            'blank cell' => ['   '],
            'null cell' => [null],
            'bare @' => ['@'],
            'video URL' => ['https://www.youtube.com/watch?v=rie-hPVJ7Sw'],
            'other host' => ['https://example.com/@Google'],
            'URL without scheme' => ['youtube.com/@Google'],
            '/channel/ without ID' => ['https://www.youtube.com/channel/'],
            'short Channel ID' => ['UCk1SpWNzOs4MYmr0uICEnt'],
        ];
    }

    #[DataProvider('unsupportedInputs')]
    public function test_unsupported_inputs_send_no_request(?string $input): void
    {
        $this->assertSame(ChannelInputResolver::UNSUPPORTED, (new ChannelInputResolver)->resolve($input));
        $this->assertSame([], $this->fake->requests);
    }

    public function test_lookup_without_a_channel_gives_not_found(): void
    {
        $this->assertSame(ChannelInputResolver::NOT_FOUND, (new ChannelInputResolver)->resolve('UCk1SpWNzOs4MYmr0uICEntg'));
        $this->assertCount(1, $this->fake->requests);
    }

    public function test_no_input_sends_a_search_request(): void
    {
        foreach (array_merge(self::resolvableInputs(), self::unsupportedInputs()) as $case) {
            (new ChannelInputResolver)->resolve($case[0]);
        }

        $this->assertNotContains(ApiClient::ENDPOINTS['search.list'], array_column($this->fake->requests, 'url'));
    }

    public function test_an_injected_client_is_used_instead_of_the_shared_client(): void
    {
        $other = new FakeApiClient;

        (new ChannelInputResolver(new FakeYoutube($other)))->resolve('@Google');

        $this->assertCount(1, $other->requests);
        $this->assertSame([], $this->fake->requests);
    }
}
