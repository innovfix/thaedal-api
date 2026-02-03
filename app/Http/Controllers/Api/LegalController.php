<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\JsonResponse;

class LegalController extends Controller
{
    public function show(string $type): JsonResponse
    {
        $page = LegalPage::where('page_type', $type)->first();

        if (!$page) {
            $defaults = [
                'terms' => [
                    'title' => 'Terms and Conditions',
                    'content' => '<h1>Terms and Conditions</h1><p>Welcome to Thaedal. By using our app, you agree to these terms.</p>'
                ],
                'privacy' => [
                    'title' => 'Privacy Policy',
                    'content' => '<h1>Privacy Policy</h1><p>Your privacy is important to us. This policy explains how we collect and use your information.</p>'
                ],
                'refund' => [
                    'title' => 'Refund Policy',
                    'content' => '<h1>Refund Policy</h1><p>Subscriptions can be cancelled at any time. Refunds are processed within 5-7 business days.</p>'
                ],
                'contact' => [
                    'title' => 'Contact Us',
                    'content' => '<h1>Contact Us</h1><p>Email: support@thaedal.com</p><p>Website: https://thaedal.com</p>'
                ]
            ];

            $default = $defaults[$type] ?? ['title' => ucfirst($type), 'content' => '<p>Content not available.</p>'];

            return $this->success([
                'type' => $type,
                'title' => $default['title'],
                'content' => $default['content'],
                'updated_at' => now()->toDateTimeString()
            ]);
        }

        return $this->success([
            'type' => $page->page_type,
            'title' => $page->title,
            'content' => $page->content,
            'updated_at' => optional($page->updated_at)->toDateTimeString()
        ]);
    }
}
