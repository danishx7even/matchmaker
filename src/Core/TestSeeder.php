<?php
declare(strict_types=1);

namespace Matchmaker\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TestSeeder
 *
 * Creates 10 test users across various tiers (monthly, 1-on-1, free, event),
 * populates their matchmaking pool profiles & usermeta, and executes auto-matching for monthly users.
 *
 * @internal Dev-only.
 */
class TestSeeder {

    /**
     * @return array
     */
    public static function run(): array
    {
        global $wpdb;
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';
        $created_users = [];

        $test_data = [
            [
                'username'  => 'test_m1',
                'email'     => 'm1@example.com',
                'display'   => 'Ahmad Al-Mansoor (Monthly M)',
                'role'      => 'subscriber',
                'user_type' => 'monthly',
                'pool'      => [
                    'gender' => 'male', 'pref_gender' => 'female', 'birth_date' => '1994-04-12',
                    'preferred_age_min' => 20, 'preferred_age_max' => 35,
                    'location' => 'Riyadh', 'pref_location' => 'Riyadh,Jeddah,Dubai',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest,Hijab',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia,GCC,Jordan',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 178, 'preferred_height_min' => 155, 'preferred_height_max' => 175,
                    'job' => 'Software Engineer', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'monthly', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966501111111', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Single', 'user_education' => 'Master']
            ],
            [
                'username'  => 'test_f1',
                'email'     => 'f1@example.com',
                'display'   => 'Fatima Al-Zahra (Monthly F)',
                'role'      => 'subscriber',
                'user_type' => 'monthly',
                'pool'      => [
                    'gender' => 'female', 'pref_gender' => 'male', 'birth_date' => '1997-09-25',
                    'preferred_age_min' => 24, 'preferred_age_max' => 40,
                    'location' => 'Riyadh', 'pref_location' => 'Riyadh,Jeddah',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic,English',
                    'height_cm' => 165, 'preferred_height_min' => 170, 'preferred_height_max' => 190,
                    'job' => 'Architect', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'monthly', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966502222222', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Single', 'user_education' => 'Bachelor']
            ],
            [
                'username'  => 'test_m2',
                'email'     => 'm2@example.com',
                'display'   => 'Youssef Al-Otaibi (Monthly M2)',
                'role'      => 'subscriber',
                'user_type' => 'monthly',
                'pool'      => [
                    'gender' => 'male', 'pref_gender' => 'female', 'birth_date' => '1991-06-15',
                    'preferred_age_min' => 22, 'preferred_age_max' => 34,
                    'location' => 'Jeddah', 'pref_location' => 'Jeddah,Riyadh',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest,Hijab',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia,GCC',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 182, 'preferred_height_min' => 160, 'preferred_height_max' => 175,
                    'job' => 'Physician', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'monthly', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966503333333', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Single', 'user_education' => 'Doctorate']
            ],
            [
                'username'  => 'test_f2',
                'email'     => 'f2@example.com',
                'display'   => 'Mariam Al-Ghamdi (Monthly F2)',
                'role'      => 'subscriber',
                'user_type' => 'monthly',
                'pool'      => [
                    'gender' => 'female', 'pref_gender' => 'male', 'birth_date' => '1995-11-20',
                    'preferred_age_min' => 26, 'preferred_age_max' => 42,
                    'location' => 'Jeddah', 'pref_location' => 'Jeddah,Riyadh',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Hijab', 'pref_modesty' => 'Modest,Hijab',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic,English',
                    'height_cm' => 164, 'preferred_height_min' => 174, 'preferred_height_max' => 188,
                    'job' => 'Pharmacist', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'monthly', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966504444444', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Single', 'user_education' => 'Master']
            ],
            [
                'username'  => 'test_m3',
                'email'     => 'm3@example.com',
                'display'   => 'Khaled Al-Dosari (1-on-1 M)',
                'role'      => 'subscriber',
                'user_type' => 'one_on_one',
                'pool'      => [
                    'gender' => 'male', 'pref_gender' => 'female', 'birth_date' => '1988-02-14',
                    'preferred_age_min' => 23, 'preferred_age_max' => 36,
                    'location' => 'Riyadh', 'pref_location' => 'Riyadh,Khobar',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest,Hijab',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 180, 'preferred_height_min' => 160, 'preferred_height_max' => 172,
                    'job' => 'Business Executive', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'one_on_one', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966505555555', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Divorced', 'user_education' => 'Master']
            ],
            [
                'username'  => 'test_f3',
                'email'     => 'f3@example.com',
                'display'   => 'Noura Al-Hassan (1-on-1 F)',
                'role'      => 'subscriber',
                'user_type' => 'one_on_one',
                'pool'      => [
                    'gender' => 'female', 'pref_gender' => 'male', 'birth_date' => '1993-08-05',
                    'preferred_age_min' => 28, 'preferred_age_max' => 45,
                    'location' => 'Riyadh', 'pref_location' => 'Riyadh,Khobar',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Hijab', 'pref_modesty' => 'Modest,Hijab',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic,English',
                    'height_cm' => 168, 'preferred_height_min' => 175, 'preferred_height_max' => 188,
                    'job' => 'Consultant Doctor', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'one_on_one', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966506666666', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Single', 'user_education' => 'Doctorate']
            ],
            [
                'username'  => 'test_m4',
                'email'     => 'm4@example.com',
                'display'   => 'Zaid Al-Maktoum (1-on-1 M2)',
                'role'      => 'subscriber',
                'user_type' => 'one_on_one',
                'pool'      => [
                    'gender' => 'male', 'pref_gender' => 'female', 'birth_date' => '1990-01-30',
                    'preferred_age_min' => 22, 'preferred_age_max' => 35,
                    'location' => 'Dubai', 'pref_location' => 'Dubai,Abu Dhabi,Riyadh',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest,Hijab',
                    'origin' => 'UAE', 'pref_origin' => 'GCC,Saudi Arabia',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic,English',
                    'height_cm' => 185, 'preferred_height_min' => 162, 'preferred_height_max' => 178,
                    'job' => 'Investment Director', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'one_on_one', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+971501112233', 'user_citizenship' => 'Emirati', 'user_marital_status' => 'Single', 'user_education' => 'Master']
            ],
            [
                'username'  => 'test_f4',
                'email'     => 'f4@example.com',
                'display'   => 'Amira Al-Nahyan (1-on-1 F2)',
                'role'      => 'subscriber',
                'user_type' => 'one_on_one',
                'pool'      => [
                    'gender' => 'female', 'pref_gender' => 'male', 'birth_date' => '1994-05-18',
                    'preferred_age_min' => 27, 'preferred_age_max' => 40,
                    'location' => 'Dubai', 'pref_location' => 'Dubai,Abu Dhabi',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest',
                    'origin' => 'UAE', 'pref_origin' => 'GCC,UAE',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic,English',
                    'height_cm' => 167, 'preferred_height_min' => 175, 'preferred_height_max' => 190,
                    'job' => 'Corporate Lawyer', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'one_on_one', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+971502223344', 'user_citizenship' => 'Emirati', 'user_marital_status' => 'Single', 'user_education' => 'Master']
            ],
            [
                'username'  => 'test_m5',
                'email'     => 'm5@example.com',
                'display'   => 'Fahad Al-Qahtani (Free M)',
                'role'      => 'subscriber',
                'user_type' => 'free',
                'pool'      => [
                    'gender' => 'male', 'pref_gender' => 'female', 'birth_date' => '1995-10-10',
                    'preferred_age_min' => 20, 'preferred_age_max' => 32,
                    'location' => 'Dammam', 'pref_location' => 'Dammam,Khobar',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 176, 'preferred_height_min' => 158, 'preferred_height_max' => 170,
                    'job' => 'Senior Accountant', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'free', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966507778899', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Single', 'user_education' => 'Bachelor']
            ],
            [
                'username'  => 'test_f5',
                'email'     => 'f5@example.com',
                'display'   => 'Hessa Al-Subaie (Free F)',
                'role'      => 'subscriber',
                'user_type' => 'free',
                'pool'      => [
                    'gender' => 'female', 'pref_gender' => 'male', 'birth_date' => '1998-03-22',
                    'preferred_age_min' => 24, 'preferred_age_max' => 36,
                    'location' => 'Dammam', 'pref_location' => 'Dammam,Khobar',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Hijab', 'pref_modesty' => 'Modest,Hijab',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia',
                    'languages' => 'Arabic', 'pref_languages' => 'Arabic',
                    'height_cm' => 162, 'preferred_height_min' => 172, 'preferred_height_max' => 185,
                    'job' => 'High School Teacher', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'free', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966508889900', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Single', 'user_education' => 'Bachelor']
            ],
            [
                'username'  => 'test_m6',
                'email'     => 'm6@example.com',
                'display'   => 'Omar Al-Sayed (Free M2)',
                'role'      => 'subscriber',
                'user_type' => 'free',
                'pool'      => [
                    'gender' => 'male', 'pref_gender' => 'female', 'birth_date' => '1993-07-07',
                    'preferred_age_min' => 20, 'preferred_age_max' => 30,
                    'location' => 'Cairo', 'pref_location' => 'Cairo,Alexandria',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest',
                    'origin' => 'Egypt', 'pref_origin' => 'Egypt',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 177, 'preferred_height_min' => 158, 'preferred_height_max' => 172,
                    'job' => 'Marketing Manager', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'free', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+201011122233', 'user_citizenship' => 'Egyptian', 'user_marital_status' => 'Single', 'user_education' => 'Bachelor']
            ],
            [
                'username'  => 'test_f6',
                'email'     => 'f6@example.com',
                'display'   => 'Dina Al-Masri (Free F2)',
                'role'      => 'subscriber',
                'user_type' => 'free',
                'pool'      => [
                    'gender' => 'female', 'pref_gender' => 'male', 'birth_date' => '1996-12-12',
                    'preferred_age_min' => 25, 'preferred_age_max' => 36,
                    'location' => 'Cairo', 'pref_location' => 'Cairo',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest',
                    'origin' => 'Egypt', 'pref_origin' => 'Egypt',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 163, 'preferred_height_min' => 173, 'preferred_height_max' => 185,
                    'job' => 'UI/UX Designer', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'free', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+201022233344', 'user_citizenship' => 'Egyptian', 'user_marital_status' => 'Single', 'user_education' => 'Bachelor']
            ],
            [
                'username'  => 'test_m7',
                'email'     => 'm7@example.com',
                'display'   => 'Tariq Al-Hassan (Event M)',
                'role'      => 'subscriber',
                'user_type' => 'event',
                'pool'      => [
                    'gender' => 'male', 'pref_gender' => 'female', 'birth_date' => '1992-11-05',
                    'preferred_age_min' => 20, 'preferred_age_max' => 32,
                    'location' => 'Khobar', 'pref_location' => 'Khobar,Dammam',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 181, 'preferred_height_min' => 160, 'preferred_height_max' => 175,
                    'job' => 'Financial Analyst', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'event', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966509990011', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Single', 'user_education' => 'Master']
            ],
            [
                'username'  => 'test_f7',
                'email'     => 'f7@example.com',
                'display'   => 'Mona Al-Shammari (Event F)',
                'role'      => 'subscriber',
                'user_type' => 'event',
                'pool'      => [
                    'gender' => 'female', 'pref_gender' => 'male', 'birth_date' => '1995-02-28',
                    'preferred_age_min' => 26, 'preferred_age_max' => 38,
                    'location' => 'Khobar', 'pref_location' => 'Khobar,Dammam',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 165, 'preferred_height_min' => 175, 'preferred_height_max' => 188,
                    'job' => 'Biomedical Scientist', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'event', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966500001122', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Single', 'user_education' => 'Master']
            ],
            [
                'username'  => 'test_m8',
                'email'     => 'm8@example.com',
                'display'   => 'Hamza Al-Majali (Event M2)',
                'role'      => 'subscriber',
                'user_type' => 'event',
                'pool'      => [
                    'gender' => 'male', 'pref_gender' => 'female', 'birth_date' => '1990-09-09',
                    'preferred_age_min' => 22, 'preferred_age_max' => 34,
                    'location' => 'Amman', 'pref_location' => 'Amman,Riyadh',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest',
                    'origin' => 'Jordan', 'pref_origin' => 'Jordan,GCC',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 179, 'preferred_height_min' => 160, 'preferred_height_max' => 174,
                    'job' => 'Management Consultant', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'event', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+962791112233', 'user_citizenship' => 'Jordanian', 'user_marital_status' => 'Single', 'user_education' => 'Master']
            ],
            [
                'username'  => 'test_f8',
                'email'     => 'f8@example.com',
                'display'   => 'Rania Al-Tarawneh (Event F2)',
                'role'      => 'subscriber',
                'user_type' => 'event',
                'pool'      => [
                    'gender' => 'female', 'pref_gender' => 'male', 'birth_date' => '1994-04-04',
                    'preferred_age_min' => 27, 'preferred_age_max' => 40,
                    'location' => 'Amman', 'pref_location' => 'Amman',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Hijab', 'pref_modesty' => 'Modest,Hijab',
                    'origin' => 'Jordan', 'pref_origin' => 'Jordan',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 166, 'preferred_height_min' => 174, 'preferred_height_max' => 186,
                    'job' => 'HR Specialist', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'event', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+962792223344', 'user_citizenship' => 'Jordanian', 'user_marital_status' => 'Single', 'user_education' => 'Bachelor']
            ],
            [
                'username'  => 'test_m9',
                'email'     => 'm9@example.com',
                'display'   => 'Sami Al-Harbi (Monthly M3)',
                'role'      => 'subscriber',
                'user_type' => 'monthly',
                'pool'      => [
                    'gender' => 'male', 'pref_gender' => 'female', 'birth_date' => '1989-12-25',
                    'preferred_age_min' => 22, 'preferred_age_max' => 35,
                    'location' => 'Medina', 'pref_location' => 'Medina,Jeddah,Riyadh',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest,Hijab',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 183, 'preferred_height_min' => 160, 'preferred_height_max' => 174,
                    'job' => 'University Professor', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'monthly', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966503332211', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Single', 'user_education' => 'Doctorate']
            ],
            [
                'username'  => 'test_f9',
                'email'     => 'f9@example.com',
                'display'   => 'Sahar Al-Anzi (Monthly F3)',
                'role'      => 'subscriber',
                'user_type' => 'monthly',
                'pool'      => [
                    'gender' => 'female', 'pref_gender' => 'male', 'birth_date' => '1995-07-07',
                    'preferred_age_min' => 28, 'preferred_age_max' => 42,
                    'location' => 'Medina', 'pref_location' => 'Medina,Jeddah',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Hijab', 'pref_modesty' => 'Modest,Hijab',
                    'origin' => 'Saudi Arabia', 'pref_origin' => 'Saudi Arabia',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic,English',
                    'height_cm' => 163, 'preferred_height_min' => 174, 'preferred_height_max' => 188,
                    'job' => 'Lecturer', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'monthly', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+966504443322', 'user_citizenship' => 'Saudi', 'user_marital_status' => 'Single', 'user_education' => 'Master']
            ],
            [
                'username'  => 'test_m10',
                'email'     => 'm10@example.com',
                'display'   => 'Mansour Al-Falasi (1-on-1 M3)',
                'role'      => 'subscriber',
                'user_type' => 'one_on_one',
                'pool'      => [
                    'gender' => 'male', 'pref_gender' => 'female', 'birth_date' => '1992-03-03',
                    'preferred_age_min' => 20, 'preferred_age_max' => 33,
                    'location' => 'Abu Dhabi', 'pref_location' => 'Abu Dhabi,Dubai',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest',
                    'origin' => 'UAE', 'pref_origin' => 'UAE,GCC',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic',
                    'height_cm' => 184, 'preferred_height_min' => 160, 'preferred_height_max' => 176,
                    'job' => 'Investment Banker', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'one_on_one', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+971505556677', 'user_citizenship' => 'Emirati', 'user_marital_status' => 'Single', 'user_education' => 'Master']
            ],
            [
                'username'  => 'test_f10',
                'email'     => 'f10@example.com',
                'display'   => 'Lulwa Al-Ketbi (1-on-1 F3)',
                'role'      => 'subscriber',
                'user_type' => 'one_on_one',
                'pool'      => [
                    'gender' => 'female', 'pref_gender' => 'male', 'birth_date' => '1996-06-06',
                    'preferred_age_min' => 26, 'preferred_age_max' => 38,
                    'location' => 'Abu Dhabi', 'pref_location' => 'Abu Dhabi,Dubai',
                    'religion' => 'Islam', 'pref_religion' => 'Islam',
                    'modesty' => 'Modest', 'pref_modesty' => 'Modest',
                    'origin' => 'UAE', 'pref_origin' => 'UAE,GCC',
                    'languages' => 'Arabic,English', 'pref_languages' => 'Arabic,English',
                    'height_cm' => 166, 'preferred_height_min' => 175, 'preferred_height_max' => 190,
                    'job' => 'Public Relations Officer', 'smoking' => 'No', 'pref_smoking' => 'No',
                    'drinking' => 'No', 'pref_drinking' => 'No', 'user_type' => 'one_on_one', 'is_active' => 1
                ],
                'meta' => ['phone_number' => '+971506667788', 'user_citizenship' => 'Emirati', 'user_marital_status' => 'Single', 'user_education' => 'Bachelor']
            ]
        ];

        foreach ($test_data as $data) {
            $user_id = username_exists($data['username']);
            if (!$user_id) {
                $user_id = email_exists($data['email']);
            }

            if (!$user_id) {
                $user_id = wp_insert_user([
                    'user_login'   => $data['username'],
                    'user_email'   => $data['email'],
                    'display_name' => $data['display'],
                    'first_name'   => strtok($data['display'], ' '),
                    'user_pass'    => wp_generate_password(12, true),
                    'role'         => $data['role'],
                ]);
            }

            if (is_wp_error($user_id)) {
                continue;
            }

            $user_id = (int) $user_id;

            // 1. Save usermeta
            update_user_meta($user_id, 'user_type', $data['user_type']);
            foreach ($data['meta'] as $mk => $mv) {
                update_user_meta($user_id, $mk, $mv);
            }

            // 2. Insert into pool
            $pool_payload = array_merge(['user_id' => $user_id], $data['pool']);
            $wpdb->replace(
                $pool_table,
                $pool_payload,
                ['%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s',
                 '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );

            // 3. Trigger auto-matching for seeded user
            \Matchmaker\Core\MatchingEngine::instance()->run_matching_for_user($user_id, 'admin_manual_trigger');

            $created_users[] = [
                'user_id'   => $user_id,
                'username'  => $data['username'],
                'display'   => $data['display'],
                'user_type' => $data['user_type']
            ];
        }

        return $created_users;
    }
}
