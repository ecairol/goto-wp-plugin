<?php

namespace Jinx\Search\Strategies;

/**
 * Local search strategy - fast text matching
 */
class LocalSearchStrategy {
    
    /**
     * Perform local text-based search
     */
    public function search($query, $menu_data = null) {
        if (!$menu_data) {
            $menu_data = get_option('jinx_admin_menus', array());
        }
        
        if (empty($menu_data) || empty($query)) {
            return array();
        }
        
        $query = strtolower(trim($query));
        $results = array();
        
        foreach ($menu_data as $menu) {
            // Check parent menu
            if ($this->matchesQuery($menu['title'], $query)) {
                $results[] = array(
                    'title' => $menu['title'],
                    'url' => $menu['url'],
                );
            }
            
            // Check child menus
            if (!empty($menu['children'])) {
                foreach ($menu['children'] as $child) {
                    $child_title = $child['title'];
                    $combined_title = $menu['title'] . ' > ' . $child_title;
                    
                    if ($this->matchesQuery($child_title, $query) || 
                        $this->matchesQuery($combined_title, $query)) {
                        
                        $results[] = array(
                            'title' => $combined_title,
                            'url' => $child['url'],
                        );
                    }
                }
            }
        }
        
        // Sort by relevance (exact matches first, then partial)
        usort($results, array($this, 'sortByRelevance'));
        
        return array_slice($results, 0, 10); // Limit to 10 results
    }
    
    /**
     * Check if title matches query
     */
    private function matchesQuery($title, $query) {
        $title = strtolower($title);
        
        // Exact match
        if (strpos($title, $query) !== false) {
            return true;
        }
        
        // Word boundary match
        $words = explode(' ', $query);
        foreach ($words as $word) {
            if (strlen($word) >= 2 && strpos($title, $word) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sort results by relevance
     */
    private function sortByRelevance($a, $b) {
        $query = strtolower($this->current_query ?? '');
        $title_a = strtolower($a['title']);
        $title_b = strtolower($b['title']);
        
        // Exact matches first
        $exact_a = strpos($title_a, $query) === 0;
        $exact_b = strpos($title_b, $query) === 0;
        
        if ($exact_a && !$exact_b) return -1;
        if (!$exact_a && $exact_b) return 1;
        
        // Then by length (shorter = more relevant)
        return strlen($title_a) - strlen($title_b);
    }
}
