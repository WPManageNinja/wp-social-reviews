<?php

namespace WPSocialReviews\App\Models\Traits;

trait SearchableScope
{
	/**
     * Filter by a search string across the model's $searchable columns.
     *
     * @param \WPSocialReviews\Framework\Database\Query\Builder $query
     * @param string $search
     *
     * @return \WPSocialReviews\Framework\Database\Query\Builder
     */
    public function scopeSearchBy($query, $search)
    {
        if ($search) {
            $fields = $this->searchable;
            $term = '%' . static::escapeLikeTerm($search) . '%';
            $query->where(function ($query) use ($fields, $term) {
                $query->where(array_shift($fields), 'LIKE', $term);
                foreach ($fields as $field) {
                    $query->orWhere($field, 'LIKE', $term);
                }
            });
        }

        return $query;
    }

    /**
     * Escape % and _ for a LIKE predicate. Binding alone does not do this.
     *
     * @param string $term
     *
     * @return string
     */
    protected static function escapeLikeTerm($term)
    {
        global $wpdb;

        if (isset($wpdb) && is_object($wpdb) && method_exists($wpdb, 'esc_like')) {
            return $wpdb->esc_like($term);
        }

        return addcslashes($term, '_%\\');
    }
}
