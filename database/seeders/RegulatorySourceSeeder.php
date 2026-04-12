<?php

namespace Database\Seeders;

use App\Models\RegulatorySource;
use Illuminate\Database\Seeder;

class RegulatorySourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            [
                'name'                  => 'OFAC SDN List',
                'type'                  => 'sanctions_list',
                'jurisdiction'          => 'GLOBAL',
                'source_url'            => 'https://ofac.treasury.gov/downloads/sdn.xml',
                'check_frequency_hours' => 6,
                'last_status'           => 'pending',
                'is_active'             => true,
            ],
            [
                'name'                  => 'UK Sanctions List',
                'type'                  => 'sanctions_list',
                'jurisdiction'          => 'UK',
                'source_url'            => 'https://sanctionslist.fcdo.gov.uk/docs/UK-Sanctions-List.xml',
                'check_frequency_hours' => 6,
                'last_status'           => 'pending',
                'is_active'             => true,
            ],
            [
                'name'                  => 'UN Security Council Sanctions',
                'type'                  => 'sanctions_list',
                'jurisdiction'          => 'GLOBAL',
                'source_url'            => 'https://scsanctions.un.org/resources/xml/en/consolidated.xml',
                'check_frequency_hours' => 6,
                'last_status'           => 'pending',
                'is_active'             => true,
            ],
            [
                'name'                  => 'EU Consolidated Sanctions',
                'type'                  => 'sanctions_list',
                'jurisdiction'          => 'EU',
                'source_url'            => 'https://webgate.ec.europa.eu/fsd/fsf/public/files/xmlFullSanctionsList_1_1/content',
                'check_frequency_hours' => 24,
                'last_status'           => 'pending',
                'is_active'             => true,
            ],
            [
                'name'                  => 'Australia DFAT Sanctions',
                'type'                  => 'sanctions_list',
                'jurisdiction'          => 'AU',
                'source_url'            => 'https://www.dfat.gov.au/sites/default/files/regulation/sanctions/consolidated.csv',
                'check_frequency_hours' => 24,
                'last_status'           => 'pending',
                'is_active'             => true,
            ],
            [
                'name'                  => 'AUSTRAC',
                'type'                  => 'guidance',
                'jurisdiction'          => 'AU',
                'source_url'            => 'https://www.austrac.gov.au/sitemap.xml',
                'check_frequency_hours' => 24,
                'last_status'           => 'pending',
                'is_active'             => true,
            ],
            [
                'name'                  => 'FCA',
                'type'                  => 'guidance',
                'jurisdiction'          => 'UK',
                'source_url'            => 'https://www.fca.org.uk/news/rss.xml',
                'check_frequency_hours' => 24,
                'last_status'           => 'pending',
                'is_active'             => true,
            ],
            [
                'name'                  => 'FINTRAC',
                'type'                  => 'guidance',
                'jurisdiction'          => 'CA',
                'source_url'            => 'https://www.fintrac-canafe.gc.ca/util/feed/newsen',
                'check_frequency_hours' => 24,
                'last_status'           => 'pending',
                'is_active'             => true,
            ],
            [
                'name'                  => 'Federal Register (FinCEN)',
                'type'                  => 'guidance',
                'jurisdiction'          => 'USA',
                'source_url'            => 'https://www.federalregister.gov/api/v1/articles',
                'check_frequency_hours' => 24,
                'last_status'           => 'pending',
                'is_active'             => true,
            ],
            [
                'name'                  => 'FATF',
                'type'                  => 'fatf',
                'jurisdiction'          => 'GLOBAL',
                'source_url'            => 'https://www.fatf-gafi.org',
                'check_frequency_hours' => 0, // manual update only
                'last_status'           => 'pending',
                'is_active'             => true,
            ],
        ];

        foreach ($sources as $source) {
            RegulatorySource::updateOrCreate(
                ['name' => $source['name']],
                $source
            );
        }

        $this->command->info('✅ Seeded ' . count($sources) . ' regulatory sources.');
    }
}
