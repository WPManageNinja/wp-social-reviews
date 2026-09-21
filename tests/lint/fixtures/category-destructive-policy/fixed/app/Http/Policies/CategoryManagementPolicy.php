<?php
/** Minimal faithful Phase 24 copy from app/Http/Policies/CategoryManagementPolicy.php. */
class Phase24Fixture
{
    function verifyRequest(Request $request)
    {
        return $this->currentUserCan('wpsn_manage_reviews')
            && $this->currentUserCan('wpsn_manage_testimonials');
    }
}
