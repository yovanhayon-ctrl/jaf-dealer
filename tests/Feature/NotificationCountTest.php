<?php

namespace Tests\Feature;

use App\Models\TestDrive;
use App\Models\User;
use App\Notifications\Admin\TestDriveActivity;
use App\Notifications\TestDriveStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_unauthorized(): void
    {
        $response = $this->getJson(route('notifications.unread-count'));

        $response->assertUnauthorized();
    }

    public function test_customer_receives_unread_notification_count(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer);

        $response = $this->getJson(route('notifications.unread-count'));
        $response->assertOk()->assertJson(['unread' => 0, 'unread_label' => '']);

        // Send a notification
        $testDrive = TestDrive::factory()->recycle($customer)->create();
        $customer->notify(new TestDriveStatusChanged($testDrive));

        $response = $this->getJson(route('notifications.unread-count'));
        $response->assertOk()->assertJson(['unread' => 1, 'unread_label' => '1']);
    }

    public function test_admin_receives_unread_notification_count(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($admin);

        $response = $this->getJson(route('notifications.unread-count'));
        $response->assertOk()->assertJson(['unread' => 0, 'unread_label' => '']);

        $testDrive = TestDrive::factory()->recycle($customer)->create();
        $admin->notify(new TestDriveActivity($testDrive));

        $response = $this->getJson(route('notifications.unread-count'));
        $response->assertOk()->assertJson(['unread' => 1, 'unread_label' => '1']);
    }

    public function test_owner_without_notifications_view_receives_zero(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner);

        $response = $this->getJson(route('notifications.unread-count'));
        $response->assertOk()->assertJson(['unread' => 0, 'unread_label' => '']);
    }
}
