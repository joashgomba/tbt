<?php

namespace Database\Seeders;

use App\Enums\ComplaintStatusCode;
use App\Models\ComplaintStatus;
use App\Models\Corridor;
use App\Models\Institution;
use App\Models\Location;
use App\Models\NtbCategory;
use Illuminate\Database\Seeder;

class NtbMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [ComplaintStatusCode::Submitted, 'Submitted', 'Soumis', 1, false],
            [ComplaintStatusCode::UnderValidation, 'Under validation', 'En validation', 2, false],
            [ComplaintStatusCode::Rejected, 'Rejected', 'Rejeté', 3, true],
            [ComplaintStatusCode::AdditionalInfoRequested, 'Additional information requested', 'Informations complémentaires demandées', 4, false],
            [ComplaintStatusCode::Assigned, 'Assigned', 'Assigné', 5, false],
            [ComplaintStatusCode::InProcessing, 'In processing', 'En traitement', 6, false],
            [ComplaintStatusCode::AwaitingExternalInput, 'Awaiting external input', 'En attente externe', 7, false],
            [ComplaintStatusCode::Escalated, 'Escalated', 'Escaladé', 8, false],
            [ComplaintStatusCode::Resolved, 'Resolved', 'Résolu', 9, false],
            [ComplaintStatusCode::Closed, 'Closed', 'Clôturé', 10, true],
            [ComplaintStatusCode::Reopened, 'Reopened', 'Rouvert', 11, false],
            [ComplaintStatusCode::Archived, 'Archived', 'Archivé', 12, true],
        ];

        foreach ($statuses as [$enum, $en, $fr, $order, $terminal]) {
            ComplaintStatus::query()->updateOrCreate(
                ['code' => $enum->value],
                ['name_en' => $en, 'name_fr' => $fr, 'sort_order' => $order, 'is_terminal' => $terminal],
            );
        }

        $institutions = [
            ['Ministry of Trade and Tourism', 'ministry', 'mtt@trade.dj'],
            ['Djibouti Customs Authority', 'customs', 'customs@trade.dj'],
            ['Djibouti Port Authority', 'port', 'port@trade.dj'],
            ['National SPS Authority', 'sps', 'sps@trade.dj'],
            ['Chamber of Commerce', 'private', 'chamber@trade.dj'],
        ];

        foreach ($institutions as [$name, $type, $email]) {
            Institution::query()->updateOrCreate(
                ['institution_name' => $name],
                ['institution_type' => $type, 'contact_email' => $email, 'country_code' => 'DJ', 'status' => 'active'],
            );
        }

        $corridor = Corridor::query()->updateOrCreate(
            ['corridor_code' => 'ETH-DJ'],
            [
                'corridor_name' => 'Ethiopia–Djibouti Trade Corridor',
                'name_en' => 'Ethiopia–Djibouti Trade Corridor',
                'name_fr' => 'Corridor commercial Éthiopie–Djibouti',
                'description_en' => 'Primary land and sea trade route connecting Ethiopian exporters to Djibouti ports — the backbone of regional commerce and NTB reporting on this platform.',
                'description_fr' => 'Principale route commerciale terrestre et maritime reliant les exportateurs éthiopiens aux ports de Djibouti — l\'épine dorsale du commerce régional et du signalement OTN sur cette plateforme.',
                'origin_country' => 'Ethiopia',
                'destination_country' => 'Djibouti',
                'primary_mode' => 'multimodal',
                'distance_km' => 780,
                'transit_time_hours' => 48,
                'icon' => 'ti ti-route',
                'sort_order' => 10,
                'active' => true,
            ],
        );

        Corridor::query()->updateOrCreate(
            ['corridor_code' => 'DJ-ET'],
            [
                'corridor_name' => 'Djibouti–Ethiopia Return Corridor',
                'name_en' => 'Djibouti–Ethiopia Return Corridor',
                'name_fr' => 'Corridor retour Djibouti–Éthiopie',
                'description_en' => 'Reverse-flow corridor segment for imports, empty container returns, and inbound logistics from Djibouti ports to Ethiopian markets.',
                'description_fr' => 'Segment de corridor sens retour pour les importations, retours de conteneurs vides et logistique entrante des ports de Djibouti vers les marchés éthiopiens.',
                'origin_country' => 'Djibouti',
                'destination_country' => 'Ethiopia',
                'primary_mode' => 'road',
                'distance_km' => 780,
                'transit_time_hours' => 36,
                'icon' => 'ti ti-truck',
                'sort_order' => 20,
                'active' => true,
            ],
        );

        Corridor::query()->updateOrCreate(
            ['corridor_code' => 'DJ-SO'],
            [
                'corridor_name' => 'Djibouti–Somalia Corridor',
                'name_en' => 'Djibouti–Somalia Corridor',
                'name_fr' => 'Corridor Djibouti–Somalie',
                'description_en' => 'Regional trade corridor linking Djibouti port infrastructure to Somali border markets and transit routes.',
                'description_fr' => 'Corridor commercial régional reliant l\'infrastructure portuaire de Djibouti aux marchés frontaliers somaliens et aux routes de transit.',
                'origin_country' => 'Djibouti',
                'destination_country' => 'Somalia',
                'primary_mode' => 'road',
                'distance_km' => 350,
                'transit_time_hours' => 24,
                'icon' => 'ti ti-map-pin',
                'sort_order' => 30,
                'active' => true,
            ],
        );

        $locations = [
            ['DJ-PORT', 'Port of Djibouti', 'Port de Djibouti', 'port', false, 'Djibouti', 'Djibouti City', 11.588, 43.145, 10,
                'Main maritime gateway for Ethiopia–Djibouti trade — container, bulk and general cargo operations.',
                'Principal porte maritime pour le commerce Éthiopie–Djibouti — conteneurs, vrac et marchandises générales.'],
            ['DJ-GALAFI', 'Galafi Border Post', 'Poste frontière de Galafi', 'border_post', true, 'Djibouti', 'Arta', 11.716, 41.836, 20,
                'Key land border crossing on the Ethiopia–Djibouti corridor for truck and cargo clearance.',
                'Poste frontière terrestre clé sur le corridor Éthiopie–Djibouti pour le dédouanement des camions et cargaisons.'],
            ['DJ-DORALEH', 'Doraleh Container Terminal', 'Terminal à conteneurs de Doraleh', 'terminal', false, 'Djibouti', 'Doraleh', 11.595, 43.085, 30,
                'Modern container terminal serving Ethiopian import/export flows via the Red Sea.',
                'Terminal à conteneurs moderne desservant les flux import/export éthiopiens via la mer Rouge.'],
            ['ET-DEWELLE', 'Dewelle Border Post', 'Poste frontière de Dewelle', 'border_post', true, 'Ethiopia', 'Somali Region', 11.720, 41.820, 40,
                'Ethiopian side of the Galafi–Dewelle border crossing on the main Addis Ababa–Djibouti road.',
                'Côté éthiopien du passage frontalier Galafi–Dewelle sur la route principale Addis-Abeba–Djibouti.'],
            ['ET-MODJO', 'Modjo Dry Port', 'Port sec de Modjo', 'inland_depot', false, 'Ethiopia', 'Oromia', 8.600, 39.120, 50,
                'Inland container depot and logistics hub for Ethiopian cargo before corridor transit to Djibouti.',
                'Port sec et hub logistique pour le fret éthiopien avant le transit corridor vers Djibouti.'],
        ];

        foreach ($locations as [$code, $en, $fr, $type, $border, $country, $region, $lat, $lng, $sort, $descEn, $descFr]) {
            Location::query()->updateOrCreate(
                ['location_code' => $code],
                [
                    'location_name' => $en,
                    'name_en' => $en,
                    'name_fr' => $fr,
                    'description_en' => $descEn,
                    'description_fr' => $descFr,
                    'location_type' => $type,
                    'icon' => $type === 'port' ? 'ti ti-anchor' : ($border ? 'ti ti-building-fortress' : 'ti ti-map-pin'),
                    'sort_order' => $sort,
                    'country' => $country,
                    'region' => $region,
                    'border_post' => $border,
                    'is_active' => true,
                    'corridor_id' => $corridor->id,
                    'latitude' => $lat,
                    'longitude' => $lng,
                ],
            );
        }

        $mtt = Institution::query()->where('institution_name', 'Ministry of Trade and Tourism')->first();
        $customs = Institution::query()->where('institution_name', 'Djibouti Customs Authority')->first();
        $sps = Institution::query()->where('institution_name', 'National SPS Authority')->first();

        $categories = [
            ['SPS', 'Sanitary and Phytosanitary Measures', 'Mesures sanitaires et phytosanitaires', 4, $sps?->id, 'ti ti-plant-2', 10,
                'Inspections, certifications, and quarantine measures for food, plants, and animal products at border posts.',
                'Inspections, certifications et mesures de quarantaine pour les produits alimentaires, végétaux et animaux.'],
            ['TBT', 'Technical Barriers to Trade', 'Obstacles techniques au commerce', 4, $mtt?->id, 'ti ti-ruler-measure', 20,
                'Standards, testing, labelling, and conformity assessment requirements affecting traded goods.',
                'Normes, essais, étiquetage et exigences d\'évaluation de la conformité affectant les marchandises échangées.'],
            ['CUST', 'Customs and Administrative Procedures', 'Procédures douanières et administratives', 2, $customs?->id, 'ti ti-building-warehouse', 30,
                'Delays, documentation, and procedural barriers at customs and border clearance points.',
                'Retards, documentation et obstacles procéduraux aux points de dédouanement et de passage frontalier.'],
            ['LIC', 'Licences, Permits and Authorisations', 'Licences, permis et autorisations', 2, $mtt?->id, 'ti ti-file-certificate', 40,
                'Licences, permits, registrations, and authorisations required before or during trade.',
                'Licences, permis, enregistrements et autorisations requis avant ou pendant le commerce.'],
            ['TAX', 'Taxes, Fees and Administrative Charges', 'Taxes, redevances et charges administratives', 2, $customs?->id, 'ti ti-receipt-tax', 50,
                'Unofficial or excessive fees, taxes, and administrative charges affecting traders.',
                'Redevances, taxes et charges administratives excessives ou non officielles affectant les commerçants.'],
            ['TRN', 'Transport and Logistics', 'Transport et logistique', 4, $customs?->id, 'ti ti-truck', 60,
                'Transport restrictions, logistics bottlenecks, and infrastructure-related trade barriers.',
                'Restrictions de transport, goulots d\'étranglement logistiques et obstacles liés aux infrastructures commerciales.'],
        ];

        foreach ($categories as [$code, $en, $fr, $sla, $institutionId, $icon, $sort, $descEn, $descFr]) {
            NtbCategory::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name_en' => $en,
                    'name_fr' => $fr,
                    'description_en' => $descEn,
                    'description_fr' => $descFr,
                    'icon' => $icon,
                    'sort_order' => $sort,
                    'default_sla_days' => $sla,
                    'responsible_institution_id' => $institutionId,
                    'is_active' => true,
                ],
            );
        }
    }
}
