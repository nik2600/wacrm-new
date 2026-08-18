<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        // -- Seed groups (idempotent on user_group label) --
        $groupSpecs = [
            ['user_group' => 'VIP customers',     'note' => 'Top buyers and repeat customers.',  'color' => '#7B61FF'],
            ['user_group' => 'New signups',       'note' => 'Joined within the last 30 days.',   'color' => '#0C9A88'],
            ['user_group' => 'Cart abandoners',   'note' => 'Added items but did not check out.','color' => '#E0823F'],
            ['user_group' => 'Wholesale partners','note' => 'Bulk-rate distribution partners.',  'color' => '#13478A'],
        ];

        $groups = collect($groupSpecs)->mapWithKeys(function ($spec) {
            $g = ContactGroup::firstOrCreate(
                ['user_group' => $spec['user_group']],
                ['note' => $spec['note'], 'color' => $spec['color']]
            );
            return [$spec['user_group'] => $g];
        });

        $vip       = (string) $groups['VIP customers']->id;
        $newGrp    = (string) $groups['New signups']->id;
        $cart      = (string) $groups['Cart abandoners']->id;
        $wholesale = (string) $groups['Wholesale partners']->id;

        // -- Sample contacts (obviously fake but realistic) --
        $contacts = [
            ['Ms','Aisha','','Khan','English','+91','9810045671','aisha.khan@example.com','Loves Mother\'s Day drops.',[$vip,$newGrp]],
            ['Mr','Riya','','Sharma','Hindi','+91','9810233412','riya.sharma@example.com','Repeat buyer – fragrance line.',[$vip]],
            ['Mr','Marco','','Bianchi','Italian','+39','3201148821','marco.bianchi@example.com','Subscribed via newsletter.',[$vip]],
            ['Ms','Zara','','Okafor','English','+234','8094411201','zara.okafor@example.com','Interested in wholesale pricing.',[$wholesale]],
            ['Mr','Rohan','','Gupta','Hindi','+91','7410455120','rohan.gupta@example.com','New signup – Mailchimp import.',[$newGrp]],
            ['Ms','Luna','','Park','Korean','+82','1044321188','luna.park@example.com','Cart abandoner – completed later.',[$cart]],
            ['Ms','Priya','','Menon','English','+91','9810422441','priya.menon@example.com','VIP – early access list.',[$vip,$newGrp]],
            ['Mr','Arjun','','Verma','Hindi','+91','9988776655','arjun.verma@example.com','Awaiting first order.',[$newGrp]],
            ['Ms','Sofia','','Rossi','Italian','+39','3489921144','sofia.rossi@example.com','Cart abandoned 3 days ago.',[$cart]],
            ['Mr','Daniel','','Müller','German','+49','1762345678','daniel.mueller@example.com','Distributor – DACH region.',[$wholesale]],
        ];

        foreach ($contacts as [$title,$first,$middle,$last,$lang,$cc,$mobile,$email,$memo,$gids]) {
            $name = trim(implode(' ', array_filter([$title, $first, $middle, $last])));
            Contact::updateOrCreate(
                ['email' => $email],
                [
                    'user_id'       => null,
                    'title'         => $title,
                    'first_name'    => $first,
                    'middle_name'   => $middle ?: null,
                    'last_name'     => $last,
                    'name'          => $name,
                    'language'      => $lang,
                    'address'       => null,
                    'contact_group' => $gids,
                    'country_code'  => $cc,
                    'mobile'        => $cc . ' ' . $mobile,
                    'msg'           => $memo,
                ]
            );
        }
    }
}
