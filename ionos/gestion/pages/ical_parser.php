<?php
/**
 * iCal Parser for importing calendar events from Airbnb, Booking.com, etc.
 * Supports standard iCalendar format (RFC 5545)
 */

class ICalParser {
    private $events = [];
    private $errors = [];
    private $rawData = '';

    /**
     * Parse iCal data from a URL
     */
    public function parseFromUrl($url, $timeout = 30) {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => 'Mozilla/5.0 (compatible; PropertyManager/1.0)',
                'follow_location' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $icalData = @file_get_contents($url, false, $context);

        if ($icalData === false) {
            $error = error_get_last();
            throw new Exception('Failed to fetch iCal from URL: ' . ($error['message'] ?? 'Unknown error'));
        }

        return $this->parseFromString($icalData);
    }

    /**
     * Parse iCal data from a string
     */
    public function parseFromString($icalData) {
        $this->rawData = $icalData;
        $this->events = [];
        $this->errors = [];

        // Normalize line endings
        $icalData = str_replace(["\r\n", "\r"], "\n", $icalData);

        // Unfold lines (lines starting with space or tab are continuations)
        $icalData = preg_replace("/\n[ \t]/", "", $icalData);

        $lines = explode("\n", $icalData);

        $inEvent = false;
        $currentEvent = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Start of event
            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $currentEvent = [];
                continue;
            }

            // End of event
            if ($line === 'END:VEVENT') {
                if ($inEvent && !empty($currentEvent)) {
                    $this->events[] = $this->processEvent($currentEvent);
                }
                $inEvent = false;
                $currentEvent = [];
                continue;
            }

            // Parse event properties
            if ($inEvent) {
                $this->parseLine($line, $currentEvent);
            }
        }

        return $this->events;
    }

    /**
     * Parse a single iCal line
     */
    private function parseLine($line, &$event) {
        // Split on first colon, handling parameters
        if (strpos($line, ':') === false) {
            return;
        }

        list($key, $value) = explode(':', $line, 2);

        // Handle parameters (e.g., DTSTART;TZID=America/New_York:20230101T120000)
        $params = [];
        if (strpos($key, ';') !== false) {
            $parts = explode(';', $key);
            $key = $parts[0];

            for ($i = 1; $i < count($parts); $i++) {
                if (strpos($parts[$i], '=') !== false) {
                    list($paramKey, $paramValue) = explode('=', $parts[$i], 2);
                    $params[$paramKey] = $paramValue;
                }
            }
        }

        $event[$key] = [
            'value' => $value,
            'params' => $params
        ];
    }

    /**
     * Process an event array into a normalized format
     */
    private function processEvent($eventData) {
        $event = [
            'uid' => $this->getValue($eventData, 'UID'),
            'summary' => $this->getValue($eventData, 'SUMMARY'),
            'description' => $this->getValue($eventData, 'DESCRIPTION'),
            'start_date' => $this->parseDate($eventData, 'DTSTART'),
            'end_date' => $this->parseDate($eventData, 'DTEND'),
            'created' => $this->parseDate($eventData, 'CREATED'),
            'last_modified' => $this->parseDate($eventData, 'LAST-MODIFIED'),
            'status' => $this->getValue($eventData, 'STATUS', 'CONFIRMED'),
            'location' => $this->getValue($eventData, 'LOCATION'),
            'organizer' => $this->parseOrganizer($eventData),
            'attendees' => $this->parseAttendees($eventData),
            'raw_data' => $eventData
        ];

        // Extract guest information from summary or description
        $event = array_merge($event, $this->extractGuestInfo($event));

        // Detect if it's a blocked period (no guest info)
        $event['is_blocked'] = $this->isBlockedPeriod($event);

        // Calculate number of nights
        if ($event['start_date'] && $event['end_date']) {
            $start = new DateTime($event['start_date']);
            $end = new DateTime($event['end_date']);
            $event['num_nights'] = $start->diff($end)->days;
        } else {
            $event['num_nights'] = 0;
        }

        return $event;
    }

    /**
     * Get value from event data
     */
    private function getValue($eventData, $key, $default = null) {
        if (isset($eventData[$key])) {
            $value = $eventData[$key]['value'];
            // Decode escaped characters
            $value = str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $value);
            return $value;
        }
        return $default;
    }

    /**
     * Parse iCal date/datetime
     */
    private function parseDate($eventData, $key) {
        if (!isset($eventData[$key])) {
            return null;
        }

        $dateString = $eventData[$key]['value'];
        $params = $eventData[$key]['params'];

        // Remove timezone suffix if present (e.g., 20230101T120000Z)
        $dateString = str_replace('Z', '', $dateString);

        // Parse different date formats
        if (strlen($dateString) === 8) {
            // Date only: YYYYMMDD
            return DateTime::createFromFormat('Ymd', $dateString)->format('Y-m-d');
        } elseif (strlen($dateString) === 15) {
            // DateTime: YYYYMMDDTHHmmss
            $dt = DateTime::createFromFormat('Ymd\THis', $dateString);
            return $dt ? $dt->format('Y-m-d H:i:s') : null;
        }

        return null;
    }

    /**
     * Parse organizer information
     */
    private function parseOrganizer($eventData) {
        if (!isset($eventData['ORGANIZER'])) {
            return null;
        }

        $organizer = $eventData['ORGANIZER']['value'];
        $params = $eventData['ORGANIZER']['params'];

        // Extract email from mailto: URI
        if (strpos($organizer, 'mailto:') === 0) {
            $email = substr($organizer, 7);
            $name = $params['CN'] ?? null;

            return [
                'email' => $email,
                'name' => $name
            ];
        }

        return ['raw' => $organizer];
    }

    /**
     * Parse attendees
     */
    private function parseAttendees($eventData) {
        $attendees = [];

        // iCal can have multiple ATTENDEE fields, but our simple parser
        // stores only the last one. For a full implementation, we'd need
        // to handle multiple values per key.

        return $attendees;
    }

    /**
     * Extract guest information from summary and description
     */
    private function extractGuestInfo($event) {
        $guestInfo = [
            'guest_name' => null,
            'guest_email' => null,
            'guest_phone' => null,
            'platform_reservation_id' => null
        ];

        $text = ($event['summary'] ?? '') . ' ' . ($event['description'] ?? '');

        // Extract email
        if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $text, $matches)) {
            $guestInfo['guest_email'] = $matches[1];
        }

        // Extract phone (various formats)
        if (preg_match('/(\+?[0-9]{1,3}[-.\s]?)?(\(?[0-9]{2,4}\)?[-.\s]?[0-9]{2,4}[-.\s]?[0-9]{2,4}[-.\s]?[0-9]{0,4})/', $text, $matches)) {
            $phone = preg_replace('/[^0-9+]/', '', $matches[0]);
            if (strlen($phone) >= 10) {
                $guestInfo['guest_phone'] = $matches[0];
            }
        }

        // Extract Airbnb reservation code (e.g., HM123456789)
        if (preg_match('/\b(HM[A-Z0-9]{9,})\b/', $text, $matches)) {
            $guestInfo['platform_reservation_id'] = $matches[1];
        }

        // Extract Booking.com reservation number
        if (preg_match('/\b([0-9]{9,10})\b/', $text, $matches)) {
            if (!$guestInfo['platform_reservation_id']) {
                $guestInfo['platform_reservation_id'] = $matches[1];
            }
        }

        // Extract guest name from summary
        // Common formats:
        // "Reserved" or "Blocked" = no guest
        // "John Doe" or "Reservation for John Doe"
        $summary = $event['summary'] ?? '';

        if (!empty($summary) &&
            stripos($summary, 'reserved') === false &&
            stripos($summary, 'blocked') === false &&
            stripos($summary, 'not available') === false) {

            // Remove common prefixes
            $name = preg_replace('/^(Reserved for|Reservation for|Réservé par|Réservation de)\s*/i', '', $summary);
            $name = trim($name);

            if (!empty($name) && strlen($name) > 2) {
                $guestInfo['guest_name'] = $name;
            }
        }

        return $guestInfo;
    }

    /**
     * Detect if this is a blocked period rather than a reservation
     */
    private function isBlockedPeriod($event) {
        $summary = strtolower($event['summary'] ?? '');
        $description = strtolower($event['description'] ?? '');

        $blockKeywords = [
            'blocked',
            'not available',
            'unavailable',
            'busy',
            'reserved',
            'bloqué',
            'indisponible',
            'occupé'
        ];

        foreach ($blockKeywords as $keyword) {
            if (stripos($summary, $keyword) !== false) {
                // If there's also guest info, it's probably a reservation
                if (!empty($event['guest_name']) || !empty($event['guest_email'])) {
                    return false;
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Get all parsed events
     */
    public function getEvents() {
        return $this->events;
    }

    /**
     * Get events count
     */
    public function getEventCount() {
        return count($this->events);
    }

    /**
     * Get raw iCal data
     */
    public function getRawData() {
        return $this->rawData;
    }

    /**
     * Get errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Filter events by date range
     */
    public function filterByDateRange($startDate, $endDate) {
        return array_filter($this->events, function($event) use ($startDate, $endDate) {
            $eventStart = $event['start_date'];
            $eventEnd = $event['end_date'];

            if (!$eventStart || !$eventEnd) {
                return false;
            }

            return $eventStart <= $endDate && $eventEnd >= $startDate;
        });
    }

    /**
     * Get upcoming events
     */
    public function getUpcomingEvents() {
        $today = date('Y-m-d');

        return array_filter($this->events, function($event) use ($today) {
            return $event['start_date'] && $event['start_date'] >= $today;
        });
    }

    /**
     * Get past events
     */
    public function getPastEvents() {
        $today = date('Y-m-d');

        return array_filter($this->events, function($event) use ($today) {
            return $event['end_date'] && $event['end_date'] < $today;
        });
    }
}