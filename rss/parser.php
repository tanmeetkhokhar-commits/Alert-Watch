<?php


final class AlertWatchRssParser
{
    private const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    
    public function parse(string $xml, string $sourceCategory = ''): array
    {
        $xml = trim($xml);

        if ($xml === '') {
            throw new RuntimeException('RSS document is empty.');
        }

        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($feed === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            $message = 'Invalid RSS/Atom XML.';
            if ($errors) {
                $message .= ' ' . trim($errors[0]->message);
            }

            throw new RuntimeException($message);
        }

        $items = [];

        if (isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $items[] = $this->parseRssItem($item, $sourceCategory);
            }
        } elseif (isset($feed->entry)) {
            foreach ($feed->entry as $entry) {
                $items[] = $this->parseAtomEntry($entry, $sourceCategory);
            }
        } elseif (isset($feed->item)) {
            foreach ($feed->item as $item) {
                $items[] = $this->parseRssItem($item, $sourceCategory);
            }
        } else {
            throw new RuntimeException('No RSS items or Atom entries were found.');
        }

        return array_values(array_filter($items, static fn ($item) => $item['title'] !== ''));
    }

    private function parseRssItem(SimpleXMLElement $item, string $sourceCategory): array
    {
        $namespaces = $item->getName() ? $item->getDocNamespaces(true) : [];
        $geo = $this->readGeoLocation($item);

        $title = $this->text($item->title ?? '');
        $description = $this->text($item->description ?? ($item->summary ?? ($item->content ?? '')));

        $guid = $this->text($item->guid ?? ($item->id ?? ''));
        $link = $this->readLink($item);
        $published = $this->text(
            $item->pubDate ??
            ($item->published ?? ($item->updated ?? ($item->date ?? '')))
        );

        $category = $this->text($item->category ?? '');
        $location = $this->text($item->location ?? ($item->area ?? ''));
        if ($location === '') {
            $location = $geo;
        }

        $severity = $this->normalizeSeverity(
            $this->text($item->severity ?? ''),
            $title . ' ' . $description
        );

        return $this->normalize(
            $guid,
            $link,
            $title,
            $description,
            $category !== '' ? $category : $sourceCategory,
            $location,
            $severity,
            $published,
            $link
        );
    }

    private function parseAtomEntry(SimpleXMLElement $entry, string $sourceCategory): array
    {
        $title = $this->text($entry->title ?? '');
        $description = $this->text(
            $entry->summary ??
            ($entry->content ?? ($entry->description ?? ''))
        );

        $id = $this->text($entry->id ?? '');
        $link = $this->readLink($entry);
        $published = $this->text(
            $entry->published ??
            ($entry->updated ?? ($entry->date ?? ''))
        );

        $category = '';
        if (isset($entry->category)) {
            foreach ($entry->category as $cat) {
                $attributes = $cat->attributes();
                $term = $this->text((string)($attributes['term'] ?? ''));
                if ($term !== '') {
                    $category = $term;
                    break;
                }
                $category = $this->text($cat);
            }
        }

        $location = $this->text($entry->location ?? ($entry->area ?? ''));
        if ($location === '') {
            $location = $this->readGeoLocation($entry);
        }

        $severity = $this->normalizeSeverity(
            $this->text($entry->severity ?? ''),
            $title . ' ' . $description
        );

        return $this->normalize(
            $id,
            $link,
            $title,
            $description,
            $category !== '' ? $category : $sourceCategory,
            $location,
            $severity,
            $published,
            $link
        );
    }

    private function normalize(
        string $externalId,
        string $fallbackId,
        string $title,
        string $description,
        string $category,
        string $location,
        string $severity,
        string $published,
        string $sourceUrl
    ): array {
        $externalId = trim($externalId);

        if ($externalId === '') {
            $externalId = trim($fallbackId);
        }

        if ($externalId === '') {
            $externalId = 'rss-' . sha1(
                strtolower($title . '|' . $published . '|' . $sourceUrl)
            );
        }

        return [
            'external_id' => mb_substr($externalId, 0, 255),
            'title' => mb_substr($title, 0, 500),
            'description' => $description !== '' ? $description : null,
            'category' => $category !== '' ? mb_substr($category, 0, 100) : null,
            'location' => $location !== '' ? mb_substr($location, 0, 255) : null,
            'severity' => $severity,
            'published_at' => $this->parseDate($published),
            'source_url' => $sourceUrl !== '' ? mb_substr($sourceUrl, 0, 1000) : null,
        ];
    }

    private function readLink(SimpleXMLElement $node): string
    {
        if (isset($node->link)) {
            foreach ($node->link as $link) {
                $attributes = $link->attributes();
                $href = trim((string)($attributes['href'] ?? ''));

                if ($href !== '') {
                    $rel = strtolower(trim((string)($attributes['rel'] ?? 'alternate')));
                    if ($rel === 'alternate' || $rel === '') {
                        return $href;
                    }
                }

                $text = $this->text($link);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    private function readGeoLocation(SimpleXMLElement $node): string
    {
        $namespaces = $node->getDocNamespaces(true);

        if (isset($namespaces['georss'])) {
            $geo = $node->children($namespaces['georss']);

            if (isset($geo->point)) {
                return $this->text($geo->point);
            }

            if (isset($geo->where)) {
                return $this->text($geo->where);
            }
        }

        if (isset($namespaces['geo'])) {
            $geo = $node->children($namespaces['geo']);

            $lat = $this->text($geo->lat ?? '');
            $long = $this->text($geo->long ?? '');

            if ($lat !== '' && $long !== '') {
                return $lat . ', ' . $long;
            }
        }

        return '';
    }

    private function normalizeSeverity(string $value, string $text): string
    {
        $value = strtolower(trim($value));

        if (in_array($value, self::SEVERITIES, true)) {
            return $value;
        }

        $text = strtolower($text);

        if (preg_match('/\b(critical|life[- ]threatening|evacuation|major emergency)\b/', $text)) {
            return 'critical';
        }

        if (preg_match('/\b(severe|warning|urgent|dangerous|major)\b/', $text)) {
            return 'high';
        }

        if (preg_match('/\b(advisory|moderate|watch|notice)\b/', $text)) {
            return 'medium';
        }

        return 'low';
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function text($value): string
    {
        return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
