<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Creator;
use App\Models\SubscriptionPlan;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
        ]);
        
        $this->seedCategories();
        $this->seedCreators();
        $this->seedSubscriptionPlans();
        $this->seedVideos();
    }

    protected function seedCategories(): void
    {
        $categories = [
            ['name' => 'Share Market', 'name_tamil' => 'பங்கு சந்தை', 'slug' => 'share-market', 'icon' => 'trending_up', 'color' => '#4CAF50'],
            ['name' => 'Finance', 'name_tamil' => 'நிதி', 'slug' => 'finance', 'icon' => 'account_balance', 'color' => '#2196F3'],
            ['name' => 'Part Time Income', 'name_tamil' => 'பகுதி நேர வருமானம்', 'slug' => 'part-time-income', 'icon' => 'work', 'color' => '#FF9800'],
            ['name' => 'Arasu Info', 'name_tamil' => 'அரசு தகவல்', 'slug' => 'arasu-info', 'icon' => 'gavel', 'color' => '#9C27B0'],
            ['name' => 'YouTube', 'name_tamil' => 'யூடியூப்', 'slug' => 'youtube', 'icon' => 'play_circle', 'color' => '#F44336'],
            ['name' => 'Business', 'name_tamil' => 'வணிகம்', 'slug' => 'business', 'icon' => 'business', 'color' => '#607D8B'],
            ['name' => 'Instagram', 'name_tamil' => 'இன்ஸ்டாகிராம்', 'slug' => 'instagram', 'icon' => 'photo_camera', 'color' => '#E91E63'],
            ['name' => 'English Speaking', 'name_tamil' => 'ஆங்கிலம் பேசுதல்', 'slug' => 'english-speaking', 'icon' => 'translate', 'color' => '#00BCD4'],
        ];

        foreach ($categories as $index => $category) {
            Category::create([
                'id' => Str::uuid(),
                'name' => $category['name'],
                'name_tamil' => $category['name_tamil'],
                'slug' => $category['slug'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }

    protected function seedCreators(): void
    {
        $creators = [
            ['name' => 'Thaedal Official', 'bio' => 'Official Thaedal channel for premium educational content', 'is_verified' => true],
            ['name' => 'Finance Guru', 'bio' => 'Expert in personal finance and investment strategies', 'is_verified' => true],
            ['name' => 'Stock Master', 'bio' => 'Technical analysis and stock market insights', 'is_verified' => false],
            ['name' => 'Business Coach', 'bio' => 'Helping entrepreneurs build successful businesses', 'is_verified' => true],
        ];

        foreach ($creators as $creator) {
            Creator::create([
                'id' => Str::uuid(),
                'name' => $creator['name'],
                'bio' => $creator['bio'],
                'is_verified' => $creator['is_verified'],
                'is_active' => true,
            ]);
        }
    }

    protected function seedSubscriptionPlans(): void
    {
        $plans = [
            [
                'name' => 'Monthly',
                'name_tamil' => 'மாதாந்திர',
                'description' => 'Access all premium content for 1 month',
                'price' => 299,
                'duration_days' => 30,
                'duration_type' => 'monthly',
                'trial_days' => 7,
                'features' => ['Unlimited video access', 'Download videos', 'Ad-free experience', 'Priority support'],
                'is_popular' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Yearly',
                'name_tamil' => 'வருடாந்திர',
                'description' => 'Best value! Access all premium content for 1 year',
                'price' => 1999,
                'original_price' => 3588,
                'discount_percentage' => 44,
                'duration_days' => 365,
                'duration_type' => 'yearly',
                'trial_days' => 7,
                'features' => ['Unlimited video access', 'Download videos', 'Ad-free experience', 'Priority support', 'Exclusive content', 'Early access to new videos'],
                'is_popular' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Lifetime',
                'name_tamil' => 'வாழ்நாள்',
                'description' => 'One-time payment for lifetime access',
                'price' => 4999,
                'original_price' => 9999,
                'discount_percentage' => 50,
                'duration_days' => 36500, // 100 years
                'duration_type' => 'lifetime',
                'trial_days' => 0,
                'features' => ['Unlimited video access', 'Download videos', 'Ad-free experience', 'Priority support', 'Exclusive content', 'Early access to new videos', 'One-on-one mentorship sessions'],
                'is_popular' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create([
                'id' => Str::uuid(),
                ...$plan,
                'currency' => 'INR',
                'is_active' => true,
            ]);
        }
    }

    protected function seedVideos(): void
    {
        $categories = Category::all();
        $creators = Creator::all();

        $sampleVideos = [
            ['title' => 'Stock Market Basics for Beginners', 'duration' => 1200],
            ['title' => 'How to Start Investing in 2024', 'duration' => 900],
            ['title' => 'Top 5 Mutual Funds to Invest', 'duration' => 750],
            ['title' => 'Personal Finance Tips', 'duration' => 600],
            ['title' => 'Start Your YouTube Channel', 'duration' => 1500],
            ['title' => 'Instagram Growth Strategies', 'duration' => 1100],
            ['title' => 'Business Ideas for 2024', 'duration' => 1300],
            ['title' => 'English Speaking Practice', 'duration' => 800],
        ];

        foreach ($sampleVideos as $index => $videoData) {
            Video::create([
                'id' => Str::uuid(),
                'title' => $videoData['title'],
                'description' => 'This is a sample video description for ' . $videoData['title'],
                'thumbnail_url' => 'https://picsum.photos/seed/' . ($index + 1) . '/640/360',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', // Sample URL
                'video_type' => 'youtube',
                'duration' => $videoData['duration'],
                'views_count' => rand(100, 10000),
                'likes_count' => rand(10, 1000),
                'category_id' => $categories->random()->id,
                'creator_id' => $creators->random()->id,
                'is_premium' => rand(0, 1) === 1,
                'is_published' => true,
                'is_featured' => $index < 3,
                'published_at' => now()->subDays(rand(0, 30)),
            ]);
        }
    }
}

