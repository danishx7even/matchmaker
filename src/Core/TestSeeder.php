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
                'username'  => 'test_monthly_male',
                'email'     => 'monthly_male@example.com',
                'display'   => 'Ahmad Al-Mansoor (Monthly M)',
                'role'      => 'subscriber',
                'user_type' => 'monthly',
                'pool'      => [
                    'gender'               => 'male',
                    'pref_gender'          => 'female',
                    'birth_date'           => '1995-04-12',
                    'preferred_age_min'    => 20,
                    'preferred_age_max'    => 35,
                    'location'             => 'Riyadh',
                    'pref_location'        => 'Riyadh,Jeddah',
                    'religion'             => 'Islam',
                    'pref_religion'        => 'Islam',
                    'modesty'              => 'Modest',
                    'pref_modesty'         => 'Modest,Hijab',
                    'origin'               => 'Saudi Arabia',
                    'pref_origin'          => 'Saudi Arabia,GCC',
                    'languages'            => 'Arabic,English',
                    'pref_languages'       => 'Arabic',
                    'height_cm'            => 178,
                    'preferred_height_min' => 155,
                    'preferred_height_max' => 175,
                    'job'                  => 'Software Engineer',
                    'smoking'              => 'No',
                    'pref_smoking'         => 'No',
                    'drinking'             => 'No',
                    'pref_drinking'        => 'No',
                    'user_type'            => 'monthly',
                    'is_active'            => 1,
                ],
                'meta' => [
                    'phone_number'         => '+966501234567',
                    'user_citizenship'     => 'Saudi',
                    'user_marital_status'  => 'Single',
                    'user_children'        => 'No',
                    'user_prayer'          => 'Always',
                    'user_education'       => 'Master',
                    'user_income'          => '$60,000 - $80,000',
                    'pref_marital_status'  => 'Single',
                    'pref_children'        => 'Open',
                    'pref_additional_info' => 'Looking for a pious, educated partner to build a loving home.',
                ]
            ],
            [
                'username'  => 'test_monthly_female',
                'email'     => 'monthly_female@example.com',
                'display'   => 'Fatima Al-Zahra (Monthly F)',
                'role'      => 'subscriber',
                'user_type' => 'monthly',
                'pool'      => [
                    'gender'               => 'female',
                    'pref_gender'          => 'male',
                    'birth_date'           => '1998-09-25',
                    'preferred_age_min'    => 25,
                    'preferred_age_max'    => 40,
                    'location'             => 'Riyadh',
                    'pref_location'        => 'Riyadh',
                    'religion'             => 'Islam',
                    'pref_religion'        => 'Islam',
                    'modesty'              => 'Modest',
                    'pref_modesty'         => 'Modest',
                    'origin'               => 'Saudi Arabia',
                    'pref_origin'          => 'Saudi Arabia',
                    'languages'            => 'Arabic,English',
                    'pref_languages'       => 'Arabic,English',
                    'height_cm'            => 165,
                    'preferred_height_min' => 170,
                    'preferred_height_max' => 190,
                    'job'                  => 'Architect',
                    'smoking'              => 'No',
                    'pref_smoking'         => 'No',
                    'drinking'             => 'No',
                    'pref_drinking'        => 'No',
                    'user_type'            => 'monthly',
                    'is_active'            => 1,
                ],
                'meta' => [
                    'phone_number'         => '+966507654321',
                    'user_citizenship'     => 'Saudi',
                    'user_marital_status'  => 'Single',
                    'user_children'        => 'No',
                    'user_prayer'          => 'Always',
                    'user_education'       => 'Bachelor',
                    'user_income'          => '$40,000 - $60,000',
                    'pref_marital_status'  => 'Single',
                    'pref_children'        => 'Wants Children',
                    'pref_additional_info' => 'Seeking an ambitious and family-oriented gentleman.',
                ]
            ],
            [
                'username'  => 'test_free_female',
                'email'     => 'free_female@example.com',
                'display'   => 'Layla Al-Khoury (Free F)',
                'role'      => 'subscriber',
                'user_type' => 'free',
                'pool'      => [
                    'gender'               => 'female',
                    'pref_gender'          => 'male',
                    'birth_date'           => '2000-01-15',
                    'preferred_age_min'    => 22,
                    'preferred_age_max'    => 35,
                    'location'             => 'Jeddah',
                    'pref_location'        => 'Jeddah,Riyadh',
                    'religion'             => 'Islam',
                    'pref_religion'        => 'Islam',
                    'modesty'              => 'Modest',
                    'pref_modesty'         => 'Modest',
                    'origin'               => 'Jordan',
                    'pref_origin'          => 'Any',
                    'languages'            => 'Arabic',
                    'pref_languages'       => 'Arabic',
                    'height_cm'            => 160,
                    'preferred_height_min' => 170,
                    'preferred_height_max' => 185,
                    'job'                  => 'Graphic Designer',
                    'smoking'              => 'No',
                    'pref_smoking'         => 'No',
                    'drinking'             => 'No',
                    'pref_drinking'        => 'No',
                    'user_type'            => 'free',
                    'is_active'            => 1,
                ],
                'meta' => [
                    'phone_number'         => '+966509998877',
                    'user_citizenship'     => 'Jordanian',
                    'user_marital_status'  => 'Single',
                    'user_children'        => 'No',
                    'user_prayer'          => 'Always',
                    'user_education'       => 'Bachelor',
                ]
            ],
            [
                'username'  => 'test_event_male',
                'email'     => 'event_male@example.com',
                'display'   => 'Tariq Al-Hassan (Event M)',
                'role'      => 'subscriber',
                'user_type' => 'event',
                'pool'      => [
                    'gender'               => 'male',
                    'pref_gender'          => 'female',
                    'birth_date'           => '1992-11-05',
                    'preferred_age_min'    => 20,
                    'preferred_age_max'    => 32,
                    'location'             => 'Dammam',
                    'pref_location'        => 'Dammam,Riyadh',
                    'religion'             => 'Islam',
                    'pref_religion'        => 'Islam',
                    'modesty'              => 'Modest',
                    'pref_modesty'         => 'Modest',
                    'origin'               => 'Saudi Arabia',
                    'pref_origin'          => 'Saudi Arabia',
                    'languages'            => 'Arabic,English',
                    'pref_languages'       => 'Arabic',
                    'height_cm'            => 182,
                    'preferred_height_min' => 160,
                    'preferred_height_max' => 175,
                    'job'                  => 'Financial Analyst',
                    'smoking'              => 'No',
                    'pref_smoking'         => 'No',
                    'drinking'             => 'No',
                    'pref_drinking'        => 'No',
                    'user_type'            => 'event',
                    'is_active'            => 1,
                ],
                'meta' => [
                    'phone_number'         => '+966504443322',
                    'user_citizenship'     => 'Saudi',
                    'user_marital_status'  => 'Single',
                    'user_children'        => 'No',
                    'user_prayer'          => 'Always',
                    'user_education'       => 'Master',
                ]
            ],
            [
                'username'  => 'test_one_on_one_male',
                'email'     => 'one_on_one_male@example.com',
                'display'   => 'Kaled Al-Otaibi (1-on-1 M)',
                'role'      => 'subscriber',
                'user_type' => 'one_on_one',
                'pool'      => [
                    'gender'               => 'male',
                    'pref_gender'          => 'female',
                    'birth_date'           => '1989-03-20',
                    'preferred_age_min'    => 22,
                    'preferred_age_max'    => 33,
                    'location'             => 'Riyadh',
                    'pref_location'        => 'Riyadh',
                    'religion'             => 'Islam',
                    'pref_religion'        => 'Islam',
                    'modesty'              => 'Hijab',
                    'pref_modesty'         => 'Hijab',
                    'origin'               => 'Saudi Arabia',
                    'pref_origin'          => 'Saudi Arabia',
                    'languages'            => 'Arabic,English',
                    'pref_languages'       => 'Arabic',
                    'height_cm'            => 180,
                    'preferred_height_min' => 160,
                    'preferred_height_max' => 172,
                    'job'                  => 'Business Executive',
                    'smoking'              => 'No',
                    'pref_smoking'         => 'No',
                    'drinking'             => 'No',
                    'pref_drinking'        => 'No',
                    'user_type'            => 'one_on_one',
                    'is_active'            => 1,
                ],
                'meta' => [
                    'phone_number'         => '+966505556677',
                    'user_citizenship'     => 'Saudi',
                    'user_marital_status'  => 'Divorced',
                    'user_children'        => '1 Child',
                    'user_prayer'          => 'Always',
                    'user_education'       => 'Master',
                ]
            ],
            [
                'username'  => 'test_one_on_one_female',
                'email'     => 'one_on_one_female@example.com',
                'display'   => 'Noura Al-Dosari (1-on-1 F)',
                'role'      => 'subscriber',
                'user_type' => 'one_on_one',
                'pool'      => [
                    'gender'               => 'female',
                    'pref_gender'          => 'male',
                    'birth_date'           => '1996-07-14',
                    'preferred_age_min'    => 28,
                    'preferred_age_max'    => 42,
                    'location'             => 'Riyadh',
                    'pref_location'        => 'Riyadh,Jeddah',
                    'religion'             => 'Islam',
                    'pref_religion'        => 'Islam',
                    'modesty'              => 'Hijab',
                    'pref_modesty'         => 'Modest,Hijab',
                    'origin'               => 'Saudi Arabia',
                    'pref_origin'          => 'Saudi Arabia',
                    'languages'            => 'Arabic,English',
                    'pref_languages'       => 'Arabic,English',
                    'height_cm'            => 168,
                    'preferred_height_min' => 175,
                    'preferred_height_max' => 188,
                    'job'                  => 'Physician / Doctor',
                    'smoking'              => 'No',
                    'pref_smoking'         => 'No',
                    'drinking'             => 'No',
                    'pref_drinking'        => 'No',
                    'user_type'            => 'one_on_one',
                    'is_active'            => 1,
                ],
                'meta' => [
                    'phone_number'         => '+966508889900',
                    'user_citizenship'     => 'Saudi',
                    'user_marital_status'  => 'Single',
                    'user_children'        => 'No',
                    'user_prayer'          => 'Always',
                    'user_education'       => 'Doctorate',
                ]
            ],
            [
                'username'  => 'test_monthly_female2',
                'email'     => 'monthly_female2@example.com',
                'display'   => 'Reem Al-Ghamdi (Monthly F2)',
                'role'      => 'subscriber',
                'user_type' => 'monthly',
                'pool'      => [
                    'gender'               => 'female',
                    'pref_gender'          => 'male',
                    'birth_date'           => '1997-12-01',
                    'preferred_age_min'    => 26,
                    'preferred_age_max'    => 38,
                    'location'             => 'Riyadh',
                    'pref_location'        => 'Riyadh',
                    'religion'             => 'Islam',
                    'pref_religion'        => 'Islam',
                    'modesty'              => 'Modest',
                    'pref_modesty'         => 'Modest',
                    'origin'               => 'Saudi Arabia',
                    'pref_origin'          => 'Saudi Arabia',
                    'languages'            => 'Arabic,English',
                    'pref_languages'       => 'Arabic',
                    'height_cm'            => 162,
                    'preferred_height_min' => 172,
                    'preferred_height_max' => 185,
                    'job'                  => 'Data Scientist',
                    'smoking'              => 'No',
                    'pref_smoking'         => 'No',
                    'drinking'             => 'No',
                    'pref_drinking'        => 'No',
                    'user_type'            => 'monthly',
                    'is_active'            => 1,
                ],
                'meta' => [
                    'phone_number'         => '+966501112233',
                    'user_citizenship'     => 'Saudi',
                    'user_marital_status'  => 'Single',
                    'user_children'        => 'No',
                    'user_prayer'          => 'Always',
                    'user_education'       => 'Master',
                ]
            ],
            [
                'username'  => 'test_free_male',
                'email'     => 'free_male@example.com',
                'display'   => 'Omar Al-Sayed (Free M)',
                'role'      => 'subscriber',
                'user_type' => 'free',
                'pool'      => [
                    'gender'               => 'male',
                    'pref_gender'          => 'female',
                    'birth_date'           => '1994-06-18',
                    'preferred_age_min'    => 20,
                    'preferred_age_max'    => 30,
                    'location'             => 'Jeddah',
                    'pref_location'        => 'Jeddah',
                    'religion'             => 'Islam',
                    'pref_religion'        => 'Islam',
                    'modesty'              => 'Modest',
                    'pref_modesty'         => 'Modest',
                    'origin'               => 'Egypt',
                    'pref_origin'          => 'Egypt,Any',
                    'languages'            => 'Arabic',
                    'pref_languages'       => 'Arabic',
                    'height_cm'            => 175,
                    'preferred_height_min' => 158,
                    'preferred_height_max' => 170,
                    'job'                  => 'Civil Engineer',
                    'smoking'              => 'No',
                    'pref_smoking'         => 'No',
                    'drinking'             => 'No',
                    'pref_drinking'        => 'No',
                    'user_type'            => 'free',
                    'is_active'            => 1,
                ],
                'meta' => [
                    'phone_number'         => '+966502223344',
                    'user_citizenship'     => 'Egyptian',
                    'user_marital_status'  => 'Single',
                    'user_children'        => 'No',
                    'user_prayer'          => 'Always',
                    'user_education'       => 'Bachelor',
                ]
            ],
            [
                'username'  => 'test_monthly_male2',
                'email'     => 'monthly_male2@example.com',
                'display'   => 'Fahad Al-Shammari (Monthly M2)',
                'role'      => 'subscriber',
                'user_type' => 'monthly',
                'pool'      => [
                    'gender'               => 'male',
                    'pref_gender'          => 'female',
                    'birth_date'           => '1991-08-30',
                    'preferred_age_min'    => 22,
                    'preferred_age_max'    => 32,
                    'location'             => 'Khobar',
                    'pref_location'        => 'Khobar,Dammam,Riyadh',
                    'religion'             => 'Islam',
                    'pref_religion'        => 'Islam',
                    'modesty'              => 'Modest',
                    'pref_modesty'         => 'Modest',
                    'origin'               => 'Saudi Arabia',
                    'pref_origin'          => 'Saudi Arabia',
                    'languages'            => 'Arabic,English',
                    'pref_languages'       => 'Arabic',
                    'height_cm'            => 185,
                    'preferred_height_min' => 162,
                    'preferred_height_max' => 175,
                    'job'                  => 'Marketing Director',
                    'smoking'              => 'No',
                    'pref_smoking'         => 'No',
                    'drinking'             => 'No',
                    'pref_drinking'        => 'No',
                    'user_type'            => 'monthly',
                    'is_active'            => 1,
                ],
                'meta' => [
                    'phone_number'         => '+966503334455',
                    'user_citizenship'     => 'Saudi',
                    'user_marital_status'  => 'Single',
                    'user_children'        => 'No',
                    'user_prayer'          => 'Always',
                    'user_education'       => 'Bachelor',
                ]
            ],
            [
                'username'  => 'test_event_female',
                'email'     => 'event_female@example.com',
                'display'   => 'Mona Al-Qahtani (Event F)',
                'role'      => 'subscriber',
                'user_type' => 'event',
                'pool'      => [
                    'gender'               => 'female',
                    'pref_gender'          => 'male',
                    'birth_date'           => '1995-10-10',
                    'preferred_age_min'    => 27,
                    'preferred_age_max'    => 40,
                    'location'             => 'Riyadh',
                    'pref_location'        => 'Riyadh',
                    'religion'             => 'Islam',
                    'pref_religion'        => 'Islam',
                    'modesty'              => 'Modest',
                    'pref_modesty'         => 'Modest',
                    'origin'               => 'Saudi Arabia',
                    'pref_origin'          => 'Saudi Arabia',
                    'languages'            => 'Arabic,English',
                    'pref_languages'       => 'Arabic,English',
                    'height_cm'            => 164,
                    'preferred_height_min' => 174,
                    'preferred_height_max' => 188,
                    'job'                  => 'Event Coordinator',
                    'smoking'              => 'No',
                    'pref_smoking'         => 'No',
                    'drinking'             => 'No',
                    'pref_drinking'        => 'No',
                    'user_type'            => 'event',
                    'is_active'            => 1,
                ],
                'meta' => [
                    'phone_number'         => '+966506667788',
                    'user_citizenship'     => 'Saudi',
                    'user_marital_status'  => 'Single',
                    'user_children'        => 'No',
                    'user_prayer'          => 'Always',
                    'user_education'       => 'Bachelor',
                ]
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

            // 3. Trigger auto-matching ONLY for monthly users on profile creation
            if ($data['user_type'] === 'monthly') {
                if (function_exists('mm_enqueue_user_matching_job')) {
                    mm_enqueue_user_matching_job($user_id, 'form_submit');
                }
            }

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
