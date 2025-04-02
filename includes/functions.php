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
 * Get weeks for current year as options for select
 */
function getWeekOptions($selectedWeek = null) {
    $currentWeek = getCurrentWeek();
    $currentYear = getCurrentYear();
    $options = '';
    
    // Generate options for all weeks up to the current week
    for ($week = 1; $week <= $currentWeek; $week++) {
        $selected = ($week == $selectedWeek) ? 'selected' : '';
        $weekRange = formatWeekRange($week, $currentYear);
        $options .= "<option value=\"$week\" $selected>$weekRange</option>";
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
