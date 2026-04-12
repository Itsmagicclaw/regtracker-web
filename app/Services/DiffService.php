<?php

namespace App\Services;

use App\Models\RegulatorySource;
use App\Models\DetectedChange;
use App\Models\SanctionsEntry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use SequenceMatcher;

class DiffService
{
    private QAClassifierService $qaClassifier;

    public function __construct(QAClassifierService $qaClassifier)
    {
        $this->qaClassifier = $qaClassifier;
    }

    /**
     * Detect changes between previous and current content snapshots.
     * This is the main entry point for all scraper commands.
     */
    public function detectChanges(RegulatorySource $source, string $previousContent, string $currentContent): void
    {
        try {
            // Extract meaningful data based on source type
            $previousData = $this->parseContent($source->source_type, $previousContent);
            $currentData = $this->parseContent($source->source_type, $currentContent);

            // Find added, removed, and modified entries
            $added = array_diff_key($currentData, $previousData);
            $removed = array_diff_key($previousData, $currentData);
            $modified = $this->findModifiedEntries($previousData, $currentData);

            // Log summary
            Log::info('Diff analysis completed', [
                'source' => $source->source_type,
                'added' => count($added),
                'removed' => count($removed),
                'modified' => count($modified),
            ]);

            // Create DetectedChange records for each difference
            $this->createChangeRecords($source, $added, 'added');
            $this->createChangeRecords($source, $removed, 'removed');
            $this->createChangeRecords($source, $modified, 'modified');

        } catch (\Exception $e) {
            Log::error('DiffService error', [
                'source' => $source->source_type,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse content based on source type to extract structured data.
     * Returns array of [key => data] for comparison.
     */
    private function parseContent(string $sourceType, string $content): array
    {
        $data = [];

        switch ($sourceType) {
            case 'ofac':
                $data = $this->parseOfacSdnList($content);
                break;
            case 'uk_sanctions':
                $data = $this->parseUkSanctionsList($content);
                break;
            case 'un_sanctions':
                $data = $this->parseUnSanctionsList($content);
                break;
            case 'eu_sanctions':
                $data = $this->parseEuSanctionsList($content);
                break;
            case 'dfat':
                $data = $this->parseDfatList($content);
                break;
            case 'austrac':
                $data = $this->parseAustracList($content);
                break;
            case 'fca':
                $data = $this->parseFcaRegister($content);
                break;
            case 'fintrac':
                $data = $this->parseFintracGuidance($content);
                break;
            case 'federal_register':
                $data = $this->parseFederalRegister($content);
                break;
            default:
                Log::warning("Unknown source type for parsing: {$sourceType}");
        }

        return $data;
    }

    /**
     * Parse OFAC SDN List (CSV format).
     */
    private function parseOfacSdnList(string $content): array
    {
        $data = [];
        $lines = explode("\n", $content);

        // Skip header line (Entity ID,Entity Number,Type,Programs,Name,Title,...)
        foreach (array_slice($lines, 1) as $line) {
            $fields = str_getcsv($line);
            if (count($fields) < 5 || empty($fields[0])) {
                continue;
            }

            $key = trim($fields[0]) . '-' . trim($fields[4]); // EntityID-Name
            $data[$key] = [
                'entity_id' => trim($fields[0]),
                'name' => trim($fields[4]),
                'type' => trim($fields[2] ?? ''),
                'programs' => trim($fields[3] ?? ''),
            ];
        }

        return $data;
    }

    /**
     * Parse UK Consolidated Sanctions List (CSV format).
     */
    private function parseUkSanctionsList(string $content): array
    {
        $data = [];
        $lines = explode("\n", $content);

        // Skip header
        foreach (array_slice($lines, 1) as $line) {
            $fields = str_getcsv($line);
            if (count($fields) < 3 || empty($fields[0])) {
                continue;
            }

            $key = trim($fields[0]) . '-' . trim($fields[1]);
            $data[$key] = [
                'name' => trim($fields[0]),
                'type' => trim($fields[1]),
                'regime' => trim($fields[2] ?? ''),
            ];
        }

        return $data;
    }

    /**
     * Parse UN Consolidated Sanctions Lists (XML format).
     */
    private function parseUnSanctionsList(string $content): array
    {
        $data = [];

        try {
            $xml = simplexml_load_string($content);
            if ($xml === false) {
                return $data;
            }

            foreach ($xml->DESIGNATIONS->DESIGNATION as $designation) {
                $name = (string) $designation->{'INDIVIDUAL'}->{'NAME'} ?? (string) $designation->{'ENTITY'}->{'NAME'} ?? 'Unknown';
                $key = hash('sha256', $name);

                $data[$key] = [
                    'name' => $name,
                    'un_ref' => (string) $designation->{'UN_LIST_TYPE'} ?? '',
                    'last_update' => (string) $designation->{'LAST_UPDATE'} ?? '',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('UN sanctions XML parse error: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Parse EU EEAS Sanctions Lists (JSON format).
     */
    private function parseEuSanctionsList(string $content): array
    {
        $data = [];

        try {
            $json = json_decode($content, true);
            if (!is_array($json)) {
                return $data;
            }

            // EU format: array of sanctions entries
            foreach ($json as $entry) {
                if (empty($entry['name'])) {
                    continue;
                }

                $key = hash('sha256', $entry['name']);
                $data[$key] = [
                    'name' => $entry['name'],
                    'entity_id' => $entry['entity_id'] ?? '',
                    'programme' => $entry['programme'] ?? '',
                    'update_date' => $entry['update_date'] ?? '',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('EU sanctions JSON parse error: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Parse DFAT Sanctions List (HTML with row markers).
     */
    private function parseDfatList(string $content): array
    {
        $data = [];

        // Extract rows based on HTML table structure
        if (preg_match_all('/<tr[^>]*>.*?<\/tr>/is', $content, $rows)) {
            foreach ($rows[0] as $row) {
                if (preg_match_all('/<td[^>]*>([^<]+)<\/td>/i', $row, $cells)) {
                    if (count($cells[1]) >= 2) {
                        $name = trim($cells[1][0]);
                        $type = trim($cells[1][1] ?? '');

                        if (!empty($name)) {
                            $key = hash('sha256', $name);
                            $data[$key] = [
                                'name' => $name,
                                'type' => $type,
                            ];
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Parse AUSTRAC Lists (HTML with entity divs).
     */
    private function parseAustracList(string $content): array
    {
        $data = [];

        if (preg_match_all('/<div class="entity[^>]*>(.*?)<\/div>/is', $content, $entities)) {
            foreach ($entities[1] as $entity) {
                // Extract name from entity content
                if (preg_match('/<h\d[^>]*>([^<]+)<\/h\d>/i', $entity, $match)) {
                    $name = trim($match[1]);
                    if (!empty($name)) {
                        $key = hash('sha256', $name);
                        $data[$key] = [
                            'name' => $name,
                            'content_hash' => hash('sha256', $entity),
                        ];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Parse FCA Register (JSON with firm entries).
     */
    private function parseFcaRegister(string $content): array
    {
        $data = [];

        try {
            $json = json_decode($content, true);
            if (!is_array($json)) {
                return $data;
            }

            // FCA format: array with 'data' key containing firms
            $firms = $json['data'] ?? $json;
            if (is_array($firms)) {
                foreach ($firms as $firm) {
                    if (!empty($firm['firmName'])) {
                        $key = hash('sha256', $firm['firmName']);
                        $data[$key] = [
                            'name' => $firm['firmName'],
                            'firm_ref' => $firm['firmRef'] ?? '',
                            'status' => $firm['status'] ?? '',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('FCA register JSON parse error: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Parse FINTRAC Guidance (HTML with article markers).
     */
    private function parseFintracGuidance(string $content): array
    {
        $data = [];

        if (preg_match_all('/<article[^>]*>(.*?)<\/article>/is', $content, $articles)) {
            foreach ($articles[1] as $article) {
                // Extract title or primary text
                if (preg_match('/<h\d[^>]*>([^<]+)<\/h\d>/i', $article, $match)) {
                    $title = trim($match[1]);
                } else {
                    $title = substr(strip_tags($article), 0, 100);
                }

                if (!empty($title)) {
                    $key = hash('sha256', $title);
                    $data[$key] = [
                        'title' => $title,
                        'content_hash' => hash('sha256', $article),
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Parse Federal Register (JSON with documents).
     */
    private function parseFederalRegister(string $content): array
    {
        $data = [];

        try {
            $json = json_decode($content, true);
            if (!is_array($json)) {
                return $data;
            }

            $results = $json['results'] ?? $json;
            if (is_array($results)) {
                foreach ($results as $doc) {
                    if (!empty($doc['title'])) {
                        $key = hash('sha256', $doc['title']);
                        $data[$key] = [
                            'title' => $doc['title'],
                            'document_number' => $doc['document_number'] ?? '',
                            'publication_date' => $doc['publication_date'] ?? '',
                            'agencies' => $doc['agencies'] ?? [],
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Federal Register JSON parse error: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Find entries that have been modified (exist in both but with different content).
     */
    private function findModifiedEntries(array $previous, array $current): array
    {
        $modified = [];

        foreach ($current as $key => $currentEntry) {
            if (isset($previous[$key])) {
                // Entry exists in both - check if content differs
                $prevHash = hash('sha256', json_encode($previous[$key]));
                $currHash = hash('sha256', json_encode($currentEntry));

                if ($prevHash !== $currHash) {
                    $modified[$key] = $currentEntry;
                }
            }
        }

        return $modified;
    }

    /**
     * Create DetectedChange records in the database.
     */
    private function createChangeRecords(RegulatorySource $source, array $entries, string $changeType): void
    {
        foreach ($entries as $key => $entry) {
            try {
                $summary = $this->generateSummary($entry);
                $severity = $this->assessSeverity($source->source_type, $changeType, $entry);

                $change = DetectedChange::create([
                    'regulatory_source_id' => $source->id,
                    'change_type' => $changeType,
                    'entry_identifier' => $key,
                    'summary' => $summary,
                    'severity' => $severity,
                    'raw_data' => json_encode($entry),
                    'qa_status' => 'pending',
                    'detected_at' => now(),
                ]);

                // Queue for QA classification
                $this->qaClassifier->classifyChange($change);

            } catch (\Exception $e) {
                Log::error('Failed to create change record', [
                    'source' => $source->source_type,
                    'change_type' => $changeType,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Generate a human-readable summary of the change.
     */
    private function generateSummary(array $entry): string
    {
        $summary = '';

        if (!empty($entry['name'])) {
            $summary = "Name: {$entry['name']}";
        } elseif (!empty($entry['title'])) {
            $summary = "Title: {$entry['title']}";
        } else {
            $summary = json_encode($entry);
        }

        return substr($summary, 0, 500);
    }

    /**
     * Assess severity of the change based on source and type.
     */
    private function assessSeverity(string $sourceType, string $changeType, array $entry): string
    {
        // Removals are always high severity (entity was removed from sanctions list - compliance risk)
        if ($changeType === 'removed') {
            return 'high';
        }

        // Additions to sanctions lists are critical
        if ($changeType === 'added' && in_array($sourceType, ['ofac', 'uk_sanctions', 'un_sanctions', 'eu_sanctions', 'dfat', 'austrac'])) {
            return 'critical';
        }

        // Modifications to enforcement/regulatory guidance are medium
        if ($changeType === 'modified' && in_array($sourceType, ['fca', 'fintrac', 'federal_register'])) {
            return 'medium';
        }

        // Default: medium severity
        return 'medium';
    }
}
