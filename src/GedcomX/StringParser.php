<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\WebtreesApi\GedcomX;

use Gedcom\Gedcom;
use Gedcom\Parser;
use ReflectionClass;

class StringParser
{
    public static function parse(string $gedcom): ?Gedcom
    {
        // Create Reflection structure
        $reflection  = new ReflectionClass('\\Gedcom\\Parser');
        $lineBuffer  = $reflection->getProperty('lineBuffer');
        $currentLine = $reflection->getProperty('currentLine');
        $errors      = $reflection->getProperty('errors');

        // Create parser
        $parser = new Parser();

        // Adjust line endings
        $gedcom = str_replace("\r\n", "\n", $gedcom);

        // Read string into buffer
        $lineBuffer->setValue($parser, explode("\n", $gedcom));
        $currentLine->setValue($parser, 0);
        $errors->setValue($parser, []);

        //$parser->forward();

        while (!$parser->eof()) {
            $record = $parser->getCurrentLineRecord();

            if ($record === false) {
                continue;
            }

            $depth = (int) $record[0];

            // We only process 0 level records here. Sub levels are processed
            // in methods for those data types (individuals, sources, etc)

            if ($depth == 0) {
                // Although not always an identifier (HEAD,TRLR):
                if (isset($record[1])) {
                    $parser->normalizeIdentifier($record[1]);
                }

                if (isset($record[1]) && trim((string) $record[1]) == 'HEAD') {
                    \Gedcom\Parser\Head::parse($parser);
                } elseif (isset($record[2]) && trim((string) $record[2]) == 'SUBN') {
                    \Gedcom\Parser\Subn::parse($parser);
                } elseif (isset($record[2]) && trim((string) $record[2]) == 'SUBM') {
                    \Gedcom\Parser\Subm::parse($parser);
                } elseif (isset($record[2]) && $record[2] == 'SOUR') {
                    \Gedcom\Parser\Sour::parse($parser);
                } elseif (isset($record[2]) && $record[2] == 'INDI') {
                    \Gedcom\Parser\Indi::parse($parser);
                } elseif (isset($record[2]) && $record[2] == 'FAM') {
                    \Gedcom\Parser\Fam::parse($parser);
                } elseif (isset($record[2]) && str_starts_with(trim((string) $record[2]), 'NOTE')) {
                    \Gedcom\Parser\Note::parse($parser);
                } elseif (isset($record[2]) && $record[2] == 'REPO') {
                    \Gedcom\Parser\Repo::parse($parser);
                } elseif (isset($record[2]) && $record[2] == 'OBJE') {
                    \Gedcom\Parser\Obje::parse($parser);
                } elseif (isset($record[1]) && trim((string) $record[1]) == 'TRLR') {
                    // EOF
                    break;
                } else {
                    $parser->logUnhandledRecord(self::class . ' @ ' . __LINE__);
                }
            } else {
                $parser->logUnhandledRecord(self::class . ' @ ' . __LINE__);
            }

            $parser->forward();
        }

        return $parser->getGedcom();
    }
}