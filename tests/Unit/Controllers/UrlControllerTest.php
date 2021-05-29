<?php

namespace Tests\Unit\Controllers;

use App\Models\Url;
use App\Rules\StrAlphaUnderscore;
use App\Rules\StrLowercase;
use App\Rules\URL\KeywordBlacklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UrlControllerTest extends TestCase
{
    /**
     * When the guest shortens the URL, the user_id column in the Url table must be null.
     *
     * @test
     * @group u-controller
     */
    public function guestShortensUrl()
    {
        $longUrl = 'https://laravel.com';

        $this->post(route('createshortlink'), [
            'long_url' => $longUrl,
        ]);

        $url = Url::whereLongUrl($longUrl)->first();

        $this->assertSame(null, $url->user_id);
    }

    /**
     * When the User shortens the URL, the user_id column in the Url table must be filled
     * with the authenticated user id.
     *
     * @test
     * @group u-controller
     */
    public function userShortensUrl()
    {
        $user = $this->admin();
        $longUrl = 'https://laravel.com';

        $this->loginAsAdmin();

        $this->post(route('createshortlink'), [
            'long_url' => $longUrl,
        ]);

        $url = Url::whereLongUrl($longUrl)->first();

        $this->assertSame($user->id, $url->user_id);
    }

    /**
     * The keyword column in the table of Url must be filled with a value that
     * generated by UrlHub. The is_custom column must be filled with 0 / false.
     *
     * @test
     * @group u-controller
     */
    public function shortenUrlNotCustom()
    {
        $longUrl = 'https://laravel.com';

        $this->post(route('createshortlink'), [
            'long_url' => $longUrl,
        ]);

        $url = Url::whereLongUrl($longUrl)->first();

        $this->assertEquals(
            uHub('hash_length'),
            strlen($url->keyword)
        );
        $this->assertFalse($url->is_custom);
    }

    /**
     * The keyword column in the table of Url must be filled in with the value
     * given by User. The is_custom column must be filled with 1 / true.
     *
     * @test
     * @group u-controller
     */
    public function shortenUrlIsCustom()
    {
        config(['urlhub.hash_length' => 6]);

        $longUrl = 'https://laravel.com';
        $customKey = 'foo_bar';

        $this->post(route('createshortlink'), [
            'long_url'   => $longUrl,
            'custom_key' => $customKey,
        ]);

        $url = Url::whereLongUrl($longUrl)->first();
        $this->assertSame($customKey, $url->keyword);
        $this->assertTrue($url->is_custom);
    }

    /**
     * @test
     * @group u-controller
     */
    public function customKeyValidationPass()
    {
        $response = $this->post(route('home').'/validate-custom-key', [
            'keyword' => 'hello',
        ]);

        $response->assertJson(['success' => 'Available']);
    }

    /**
     * @test
     * @group u-controller
     * @dataProvider customKeyValidationFailProvider
     */
    public function customKeyValidationFail($data)
    {
        Url::factory()->create([
            'keyword' => 'laravel',
        ]);

        $request = new Request;

        $v = Validator::make($request->all(), [
            'keyword' => [
                'max:20',
                'unique:urls',
                new StrAlphaUnderscore,
                new StrLowercase,
                new KeywordBlacklist,
            ],
        ]);

        $response = $this->post(route('home').'/validate-custom-key', [
            'keyword' => $data,
        ]);

        $response->assertJson(['errors' => $v->errors()->all()]);
    }

    public function customKeyValidationFailProvider()
    {
        return [
            [str_repeat('a', 50)],
            ['FOOBAR'],
            ['foo-bar'],
            ['foo~bar'],
            ['login'],
        ];
    }
}
