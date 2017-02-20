<?php

namespace Tests;

use Faker\Generator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Railroad\Railforums\DataMappers\UserCloakDataMapper;
use Railroad\Railforums\Entities\UserCloak;
use Railroad\Railforums\ForumServiceProvider;
use Railroad\Railmap\RailmapServiceProvider;

class TestCase extends BaseTestCase
{
    /**
     * @var Generator
     */
    protected $faker;

    /**
     * @var DatabaseManager
     */
    protected $databaseManager;

    /**
     * @var UserCloakDataMapper
     */
    protected $userCloakDataMapper;

    protected function setUp()
    {
        parent::setUp();

        $this->artisan('migrate', []);

        $this->faker = $this->app->make(Generator::class);
        $this->databaseManager = $this->app->make(DatabaseManager::class);
        $this->userCloakDataMapper = $this->app->make(UserCloakDataMapper::class);
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set(
            'database.connections.testbench',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]
        );

        $app['db']->connection()->getSchemaBuilder()->create(
            'users',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('display_name');
                $table->string('avatar_url')->nullable();
                $table->string('access_type');
            }
        );

        $app->register(RailmapServiceProvider::class);
        $app->register(ForumServiceProvider::class);
    }

    /**
     * @return UserCloak
     */
    public function fakeUserCloak()
    {
        return $this->userCloakDataMapper->fake();
    }

    /**
     * @param string $permissionLevel
     * @return UserCloak
     */
    public function fakeCurrentUserCloak($permissionLevel = UserCloak::PERMISSION_LEVEL_USER)
    {
        $userCloak = $this->userCloakDataMapper->fake($permissionLevel);

        $this->userCloakDataMapper->setCurrent($userCloak);

        return $userCloak;
    }

    /**
     * @param UserCloak $userCloak
     */
    public function setAuthenticatedUserCloak(UserCloak $userCloak)
    {
        $this->userCloakDataMapper->setCurrent($userCloak);
    }
}