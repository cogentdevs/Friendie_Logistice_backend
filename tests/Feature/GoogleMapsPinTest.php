<?php

namespace Tests\Feature;

use App\Services\Api\GoogleMapsPinResolver;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleMapsPinTest extends TestCase
{
    private function resolver(array $addresses=['8.8.8.8']): GoogleMapsPinResolver
    {
        $resolver=\Mockery::mock(GoogleMapsPinResolver::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $resolver->shouldReceive('addresses')->andReturn($addresses);
        return $resolver;
    }

    public static function exactPins(): array
    {
        return [
            ['24.8607343, 67.0011364',['24.8607343','67.0011364']],
            ['0,0',['0.0000000','0.0000000']],
            ['https://www.google.com/maps?q=24.8607343,67.0011364',['24.8607343','67.0011364']],
            ['https://maps.google.com/?q=loc%3A-33.8688197%2C151.2092955',['-33.8688197','151.2092955']],
            ['https://www.google.com/maps/search/?api=1&query=24.86%2C67.00',['24.8600000','67.0000000']],
            ['https://www.google.com/maps/place/Example/@1,2,15z/data=!4m2!3m1!3d24.860734345!4d67.001136456!16sExample',['24.8607343','67.0011365']],
        ];
    }

    #[DataProvider('exactPins')]
    public function test_explicit_pins_resolve_without_external_requests(string $value,array $expected): void
    {
        Http::fake();
        $this->assertSame($expected,$this->resolver()->resolve($value));
        Http::assertNothingSent();
    }

    public function test_short_links_follow_only_validated_redirects_with_pinned_ip_and_bounded_requests(): void
    {
        $options=[];
        Http::fake(function ($request,$config) use (&$options) {
            $options[]=$config;
            $this->assertSame('HEAD',$request->method());
            return match($request->url()) {
                'https://maps.app.goo.gl/example'=>Http::response('',302,['Location'=>'https://www.google.com/maps/redirect']),
                'https://www.google.com/maps/redirect'=>Http::response('',307,['Location'=>'/maps?q=24.86,67']),
                default=>throw new \RuntimeException('Unexpected outbound request'),
            };
        });
        $this->assertSame(['24.8600000','67.0000000'],$this->resolver()->resolve('https://maps.app.goo.gl/example'));
        Http::assertSentCount(2);
        foreach ($options as $config) {
            $this->assertFalse($config['allow_redirects']); $this->assertFalse($config['cookies']);
            $this->assertTrue($config['verify']); $this->assertSame('',$config['proxy']);
            $this->assertLessThanOrEqual(4,$config['timeout']); $this->assertLessThanOrEqual(2,$config['connect_timeout']);
            $this->assertStringEndsWith(':443:8.8.8.8',$config['curl'][CURLOPT_RESOLVE][0]);
            $this->assertSame(CURLPROTO_HTTPS,$config['curl'][CURLOPT_PROTOCOLS]);
        }
    }

    public static function invalidLinks(): array
    {
        return array_map(fn ($value)=>[$value],[
            'https://evil.example/maps?q=1,2','https://www.google.com.evil.example/maps?q=1,2',
            'https://www.google.com@127.0.0.1/maps?q=1,2','https://127.0.0.1/maps?q=1,2',
            'http://www.google.com/maps?q=1,2','https://www.google.com:8443/maps?q=1,2',
            'https://user:secret@www.google.com/maps?q=1,2','https://www.google.com/url?q=1,2',
            'https://www.google.com/maps%5c?q=1,2','https://www.google.com/maps?q=1,2#x',
            'file:///etc/passwd','gopher://www.google.com/maps?q=1,2','//www.google.com/maps?q=1,2',
            'https://goo.gl/other','https://maps.app.goo.gl/example?q=1,2',
            'https://www.google.com/maps/@24,67,10z','https://www.google.com/maps?q=An+address',
            'https://www.google.com/maps?q=91,67','https://www.google.com/maps?ll=24,67',
            'https://www.google.com/maps/dir/?origin=1,2&destination=3,4',
            'https://www.google.com/maps/dir?q=1,2',
            'https://www.google.com/maps?q=1,2&query=3,4',
            'https://www.google.com/maps/place/X/data=!3d1!4d2!3d3!4d4',
            '1e2,2','91,0','1.12345678,2',
        ]);
    }

    #[DataProvider('invalidLinks')]
    public function test_invalid_ambiguous_or_non_pin_inputs_fail_closed(string $url): void
    {
        Http::fake(fn()=>Http::response('',200));
        $this->expectException(\InvalidArgumentException::class);
        $this->resolver()->resolve($url);
    }

    public static function unsafeAddresses(): array
    {
        return [[[]],[['127.0.0.1']],[['10.1.1.1']],[['169.254.169.254']],[['192.168.1.2']],
            [['100.64.0.1']],[['0.0.0.0']],[['::1']],[['8.8.8.8','10.0.0.1']]];
    }

    #[DataProvider('unsafeAddresses')]
    public function test_private_reserved_ipv6_or_mixed_dns_answers_are_never_requested(array $addresses): void
    {
        Http::fake();
        try { $this->resolver($addresses)->resolve('https://maps.app.goo.gl/example'); $this->fail('Unsafe DNS accepted.'); }
        catch (\InvalidArgumentException) { Http::assertNothingSent(); }
    }

    public function test_cross_host_redirect_is_rejected_before_second_connection(): void
    {
        Http::fake(fn()=>Http::response('',302,['Location'=>'https://169.254.169.254/latest/meta-data/']));
        try { $this->resolver()->resolve('https://maps.app.goo.gl/example'); $this->fail('Unsafe redirect accepted.'); }
        catch (\InvalidArgumentException) { Http::assertSentCount(1); }
    }

    public function test_redirect_loop_and_hop_limit_are_bounded(): void
    {
        $count=0;
        Http::fake(function () use (&$count) { return Http::response('',302,['Location'=>'https://maps.app.goo.gl/hop'.(++$count)]); });
        try { $this->resolver()->resolve('https://maps.app.goo.gl/start'); $this->fail('Unbounded redirects.'); }
        catch (\InvalidArgumentException) { Http::assertSentCount(3); }
        Http::fake(fn()=>Http::response('',302,['Location'=>'https://maps.app.goo.gl/loop']));
        try { $this->resolver()->resolve('https://maps.app.goo.gl/loop'); $this->fail('Loop accepted.'); }
        catch (\InvalidArgumentException) { $this->assertTrue(true); }
    }
}
