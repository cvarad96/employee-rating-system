<?php
/**
 * Helper functions for the application
 */

/**
 * Format date in human readable format
 */
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

/**
 * Format date and time in human readable format
 */
function formatDateTime($date) {
    return date('M j, Y g:i A', strtotime($date));
}

/**
 * Get current week number
 */
function getCurrentWeek() {
    return date('W');
}

/**
 * Get current year
 */
function getCurrentYear() {
    return date('Y');
}

/**
 * Format week number to show its date range
 */
function formatWeekRange($week, $year) {
    // Get the date of the first day of the year
    $firstDayOfYear = new DateTime();
    $firstDayOfYear->setISODate($year, $week, 1); // Monday
    
    // Get the date of the last day of the week
    $lastDayOfWeek = clone $firstDayOfYear;
    $lastDayOfWeek->modify('+6 days'); // Sunday
    
    // Format the dates
    $formattedFirst = $firstDayOfYear->format('M j');
    $formattedLast = $lastDayOfWeek->format('M j');
    
    return "Week $week ($formattedFirst - $formattedLast)";
}

/**
 * Get weeks for current year as options for select - MODIFIED to only show last 2 weeks + current week
 */
function getWeekOptions($selectedWeek = null) {
    $currentWeek = getCurrentWeek();
    $currentYear = getCurrentYear();
    $options = '';

    // Generate array of allowed weeks (current week and previous two weeks)
    $allowedWeeks = [];

    // Add current week
    $allowedWeeks[] = [
        'week' => $currentWeek,
        'year' => $currentYear
    ];

    // Add previous two weeks
    for ($i = 1; $i <= 2; $i++) {
        $date = new DateTime();
        $date->setISODate($currentYear, $currentWeek);
        $date->modify("-$i week");

        $prevWeek = intval($date->format('W'));
        $prevYear = intval($date->format('Y'));

        $allowedWeeks[] = [
            'week' => $prevWeek,
            'year' => $prevYear
        ];
    }

    // Generate options for allowed weeks
    foreach ($allowedWeeks as $weekData) {
        $week = $weekData['week'];
        $year = $weekData['year'];
        $selected = ($week == $selectedWeek) ? 'selected' : '';
        $weekRange = formatWeekRange($week, $year);
        $options .= "<option value=\"$week\" data-year=\"$year\" $selected>$weekRange</option>";
    }

    return $options;
}

/**
 * Get ALL weeks as options for ratings overview and reports
 *
 * @param int|null $selectedWeek The currently selected week
 * @param int|null $selectedYear The currently selected year (defaults to current year)
 * @param int|null $weeksToShow Number of weeks to show (null for all available weeks)
 * @return string HTML options for select element
 */
function getAllWeekOptions($selectedWeek = null, $selectedYear = null, $weeksToShow = null) {
    $currentWeek = getCurrentWeek();
    $currentYear = getCurrentYear();
    $selectedYear = $selectedYear ?? $currentYear;
    $options = '';

    // Generate array of weeks to show
    $availableWeeks = [];

    // Generate weeks for the current year up to current week
    $maxWeek = ($selectedYear == $currentYear) ? $currentWeek : 53;

    for ($week = 1; $week <= $maxWeek; $week++) {
        // Check if this week is valid for the selected year
        $dateCheck = new DateTime();
        if (!@$dateCheck->setISODate($selectedYear, $week, 1)) {
            continue; // Skip invalid week numbers
        }

        $availableWeeks[] = [
            'week' => $week,
            'year' => $selectedYear
        ];
    }

    // Also add previous years if needed
    if ($weeksToShow === null || count($availableWeeks) < $weeksToShow) {
        // Add previous years (up to 3 years back)
        for ($prevYear = $currentYear - 1; $prevYear >= $currentYear - 3; $prevYear--) {
            // Add all weeks for this previous year
            for ($week = 1; $week <= 53; $week++) {
                $dateCheck = new DateTime();
                if (!@$dateCheck->setISODate($prevYear, $week, 1)) {
                    continue; // Skip invalid week numbers
                }

                $availableWeeks[] = [
                    'week' => $week,
                    'year' => $prevYear
                ];

                // Stop if we've reached the requested number of weeks
                if ($weeksToShow !== null && count($availableWeeks) >= $weeksToShow) {
                    break 2; // Break both loops
                }
            }
        }
    }

    // Sort from newest to oldest
    usort($availableWeeks, function($a, $b) {
        if ($a['year'] != $b['year']) {
            return $b['year'] - $a['year']; // Sort by year (descending)
        }
        return $b['week'] - $a['week']; // Then by week (descending)
    });

    // Limit number of weeks if specified
    if ($weeksToShow !== null && count($availableWeeks) > $weeksToShow) {
        $availableWeeks = array_slice($availableWeeks, 0, $weeksToShow);
    }

    // Generate options for all available weeks
    foreach ($availableWeeks as $weekData) {
        $week = $weekData['week'];
        $year = $weekData['year'];
        $selected = ($week == $selectedWeek && $year == $selectedYear) ? 'selected' : '';
        $weekRange = formatWeekRange($week, $year);

        if ($year == $currentYear) {
            $options .= "<option value=\"$week\" data-year=\"$year\" $selected>$weekRange</option>";
        } else {
            $options .= "<option value=\"$week\" data-year=\"$year\" $selected>$weekRange ($year)</option>";
        }
    }

    return $options;
}

/**
 * Get ALL weeks for the year as options for admin reports
 */
function getAdminWeekOptions($selectedWeek = null) {
    $currentWeek = getCurrentWeek();
    $currentYear = getCurrentYear();
    $options = '';

    // Generate options for all weeks in the current year
    for ($week = 1; $week <= 53; $week++) {
        // Check if this week is valid for the year
        $dateCheck = new DateTime();
        if (!@$dateCheck->setISODate($currentYear, $week, 1)) {
            continue; // Skip invalid week numbers
        }

        $selected = ($week == $selectedWeek) ? 'selected' : '';
        $weekRange = formatWeekRange($week, $currentYear);
        $options .= "<option value=\"$week\" data-year=\"$currentYear\" $selected>$weekRange</option>";
    }

    // Add previous year's weeks too
    $prevYear = $currentYear - 1;
    for ($week = 1; $week <= 53; $week++) {
        $dateCheck = new DateTime();
        if (!@$dateCheck->setISODate($prevYear, $week, 1)) {
            continue; // Skip invalid week numbers
        }

        $selected = ($week == $selectedWeek && isset($_GET['year']) && $_GET['year'] == $prevYear) ? 'selected' : '';
        $weekRange = formatWeekRange($week, $prevYear);
        $options .= "<option value=\"$week\" data-year=\"$prevYear\" $selected>$weekRange ($prevYear)</option>";
    }

    return $options;
}

/**
 * Get years as options for select
 */
function getYearOptions($selectedYear = null) {
    $currentYear = getCurrentYear();
    $options = '';
    
    // Generate options for the last 5 years
    for ($year = $currentYear; $year >= $currentYear - 4; $year--) {
        $selected = ($year == $selectedYear) ? 'selected' : '';
        $options .= "<option value=\"$year\" $selected>$year</option>";
    }
    
    return $options;
}

/**
 * Generate star rating HTML
 */
function generateStarRating($rating) {
    $html = '<div class="star-rating">';
    
    // Full stars
    for ($i = 1; $i <= floor($rating); $i++) {
        $html .= '<i class="bi bi-star-fill text-warning"></i>';
    }
    
    // Half star if applicable
    if ($rating - floor($rating) >= 0.5) {
        $html .= '<i class="bi bi-star-half text-warning"></i>';
        $i++;
    }
    
    // Empty stars
    for ($j = $i; $j <= 5; $j++) {
        $html .= '<i class="bi bi-star text-warning"></i>';
    }
    
    $html .= ' <span class="text-muted">(' . number_format($rating, 1) . ')</span>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Generate star rating input HTML
 */
function generateStarRatingInput($name, $value = 0) {
    $html = '<div class="star-rating-input">';
    
    for ($i = 1; $i <= 5; $i++) {
        $checked = ($i == $value) ? 'checked' : '';
        $html .= "<div class=\"form-check form-check-inline\">
                    <input class=\"form-check-input\" type=\"radio\" name=\"$name\" id=\"{$name}_$i\" value=\"$i\" $checked required>
                    <label class=\"form-check-label\" for=\"{$name}_$i\">$i</label>
                  </div>";
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Get color class based on rating
 */
function getRatingColorClass($rating) {
    if ($rating >= 4.5) {
        return 'success';
    } elseif ($rating >= 3.5) {
        return 'info';
    } elseif ($rating >= 2.5) {
        return 'warning';
    } else {
        return 'danger';
    }
}

/**
 * Convert rating to a percentage (1-5 scale to 0-100%)
 */
function ratingToPercentage($rating) {
    return ($rating / 5) * 100;
}

/**
 * Truncate text to a specified length
 */
function truncateText($text, $length = 50) {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . '...';
}

/**
 * Get CSS class for badge based on role
 */
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'ceo':
            return 'bg-danger';
        case 'manager':
            return 'bg-primary';
        default:
            return 'bg-secondary';
    }
}

/**
 * Format role for display
 */
function formatRole($role) {
    return ucfirst($role);
}
