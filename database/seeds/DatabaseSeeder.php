<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
    	 $this->call(OauthClientSeeder::class);
         $this->call(UserSeeder::class);
         $this->call(UserRoleSeeder::class);
    }
}
