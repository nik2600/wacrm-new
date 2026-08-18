<?php

/**
 * Curated Meta Marketing API targeting catalog.
 *
 * Design: the catalog stores INTEREST NAMES (not IDs) grouped by
 * theme. The form renders these as a Tom Select multi-select so the
 * client picks from known-good labels instead of typing free text.
 *
 * At ad-sync time the controller calls Meta's Targeting Search
 * endpoint (`/<v>/search?type=adinterest&q=<name>`) for each picked
 * name to resolve `{id, name}` pairs, then ships them to Meta under
 * `targeting.interests`. Results are cached per workspace for 7 days
 * so repeated submits don't re-hit the API.
 *
 * This is "no API from the client's perspective" — they see only the
 * dropdown. The resolution call is internal and fast (one search per
 * picked label, ~50 ms cached).
 *
 * To expand the catalog: add an entry to the right group. Keep the
 * NAME wording close to what Meta's targeting search would return
 * for that concept (e.g. "Physical fitness" not "Gym time") so the
 * resolver gets a high-confidence first match.
 *
 * Countries: ISO 3166-1 alpha-2 → display name. Used by the country
 * picker. Codes match what Meta's `geo_locations.countries` expects.
 */

return [

    'interests_groups' => [

        'Shopping & Lifestyle' => [
            'Online shopping',
            'Fashion accessories',
            'Beauty',
            'Luxury goods',
            'Home and garden',
            'Travel',
            'Pets',
            'Automobiles',
        ],

        'Health & Fitness' => [
            'Physical fitness',
            'Yoga',
            'Running',
            'Nutrition',
            'Health and wellness',
            'Meditation',
            'Cycling',
        ],

        'Food & Drink' => [
            'Cooking',
            'Restaurants',
            'Coffee',
            'Veganism',
            'Organic food',
            'Wine',
        ],

        'Family & Parenting' => [
            'Parenting',
            'Family',
            'Pregnancy',
            'Children\'s clothing',
            'Toys',
        ],

        'Business & Finance' => [
            'Entrepreneurship',
            'Small business',
            'Investing',
            'Real estate',
            'Personal finance',
            'Cryptocurrency',
            'Stock market',
        ],

        'Technology' => [
            'Technology',
            'Smartphones',
            'Computers',
            'Online communities',
            'Software',
            'Artificial intelligence',
        ],

        'Entertainment' => [
            'Music',
            'Movies',
            'Video games',
            'Sports',
            'Books',
            'Photography',
            'Television',
        ],

        'Education' => [
            'Education',
            'Higher education',
            'Online education',
            'Language learning',
            'Self-improvement',
        ],
    ],

    'countries' => [
        'IN' => 'India',
        'AE' => 'United Arab Emirates',
        'SA' => 'Saudi Arabia',
        'US' => 'United States',
        'GB' => 'United Kingdom',
        'CA' => 'Canada',
        'AU' => 'Australia',
        'NZ' => 'New Zealand',
        'SG' => 'Singapore',
        'MY' => 'Malaysia',
        'ID' => 'Indonesia',
        'TH' => 'Thailand',
        'PH' => 'Philippines',
        'VN' => 'Vietnam',
        'BD' => 'Bangladesh',
        'PK' => 'Pakistan',
        'LK' => 'Sri Lanka',
        'NP' => 'Nepal',
        'ZA' => 'South Africa',
        'NG' => 'Nigeria',
        'KE' => 'Kenya',
        'EG' => 'Egypt',
        'TR' => 'Turkey',
        'BR' => 'Brazil',
        'MX' => 'Mexico',
        'AR' => 'Argentina',
        'CO' => 'Colombia',
        'CL' => 'Chile',
        'DE' => 'Germany',
        'FR' => 'France',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'NL' => 'Netherlands',
        'SE' => 'Sweden',
        'NO' => 'Norway',
        'DK' => 'Denmark',
        'FI' => 'Finland',
        'PL' => 'Poland',
        'IE' => 'Ireland',
        'PT' => 'Portugal',
        'CH' => 'Switzerland',
        'AT' => 'Austria',
        'BE' => 'Belgium',
        'GR' => 'Greece',
        'CZ' => 'Czech Republic',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'HK' => 'Hong Kong',
        'TW' => 'Taiwan',
        'IL' => 'Israel',
        'QA' => 'Qatar',
        'KW' => 'Kuwait',
        'BH' => 'Bahrain',
        'OM' => 'Oman',
        'JO' => 'Jordan',
        'MA' => 'Morocco',
        'DZ' => 'Algeria',
        'TN' => 'Tunisia',
        'GH' => 'Ghana',
        'UG' => 'Uganda',
        'ET' => 'Ethiopia',
        'RW' => 'Rwanda',
        'TZ' => 'Tanzania',
        'RU' => 'Russia',
        'UA' => 'Ukraine',
        'RO' => 'Romania',
        'HU' => 'Hungary',
        'BG' => 'Bulgaria',
        'PE' => 'Peru',
        'UY' => 'Uruguay',
        'EC' => 'Ecuador',
        'PY' => 'Paraguay',
    ],
];
