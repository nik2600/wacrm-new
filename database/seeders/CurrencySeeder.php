<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * Top 30 commonly-used currencies — same starting set as SnapNest.
 * `exchange_rate` is a static seed value (relative to USD); admin
 * can refresh via the Fetch Rates button. USD is the system base.
 */
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['USD', 'US Dollar',              '$',     2, 1.000000],
            ['EUR', 'Euro',                   '€',     2, 0.920000],
            ['GBP', 'British Pound',          '£',     2, 0.790000],
            ['INR', 'Indian Rupee',           '₹',     2, 83.250000],
            ['AED', 'UAE Dirham',             'د.إ',  2, 3.673000],
            ['SAR', 'Saudi Riyal',            '﷼',    2, 3.750000],
            ['CAD', 'Canadian Dollar',        'CA$',   2, 1.360000],
            ['AUD', 'Australian Dollar',      'A$',    2, 1.530000],
            ['JPY', 'Japanese Yen',           '¥',     0, 149.000000],
            ['CNY', 'Chinese Yuan',           '¥',     2, 7.250000],
            ['HKD', 'Hong Kong Dollar',       'HK$',   2, 7.820000],
            ['SGD', 'Singapore Dollar',       'S$',    2, 1.350000],
            ['MYR', 'Malaysian Ringgit',      'RM',    2, 4.700000],
            ['IDR', 'Indonesian Rupiah',      'Rp',    0, 15700.000000],
            ['THB', 'Thai Baht',              '฿',     2, 36.200000],
            ['PHP', 'Philippine Peso',        '₱',     2, 56.500000],
            ['VND', 'Vietnamese Dong',        '₫',     0, 24500.000000],
            ['KRW', 'South Korean Won',       '₩',     0, 1350.000000],
            ['TRY', 'Turkish Lira',           '₺',     2, 32.500000],
            ['EGP', 'Egyptian Pound',         'E£',    2, 47.500000],
            ['ZAR', 'South African Rand',     'R',     2, 18.500000],
            ['NGN', 'Nigerian Naira',         '₦',     2, 1600.000000],
            ['KES', 'Kenyan Shilling',        'KSh',   2, 129.000000],
            ['BRL', 'Brazilian Real',         'R$',    2, 5.100000],
            ['MXN', 'Mexican Peso',           'MX$',   2, 17.300000],
            ['ARS', 'Argentine Peso',         'AR$',   2, 950.000000],
            ['CLP', 'Chilean Peso',           'CLP$',  0, 920.000000],
            ['COP', 'Colombian Peso',         'CO$',   0, 4000.000000],
            ['PKR', 'Pakistani Rupee',        '₨',     2, 278.000000],
            ['BDT', 'Bangladeshi Taka',       '৳',     2, 110.000000],
        ];

        foreach ($rows as [$code, $name, $symbol, $precision, $rate]) {
            Currency::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'symbol' => $symbol, 'precision' => $precision, 'exchange_rate' => $rate, 'is_active' => true],
            );
        }
    }
}
