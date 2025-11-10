<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    /**
     * Determine if the user can view any quotes.
     * 
     * Sales users can view their own quotes.
     * Admin users can view all quotes.
     */
    public function viewAny(User $user): bool
    {
        // Both Sales and Admin can view quotes
        return true;
    }

    /**
     * Determine if the user can view the quote.
     * 
     * Sales users can only view their own quotes.
     * Admin users can view all quotes.
     */
    public function view(User $user, Quote $quote): bool
    {
        // Admin can view all quotes
        if ($user->isAdmin()) {
            return true;
        }

        // Sales users can only view their own quotes
        return $user->id === $quote->user_id;
    }

    /**
     * Determine if the user can create quotes.
     * 
     * Both Sales and Admin can create quotes.
     */
    public function create(User $user): bool
    {
        // Both Sales and Admin can create quotes
        return true;
    }

    /**
     * Determine if the user can update the quote.
     * 
     * Sales users can only update their own quotes.
     * Admin users can update all quotes.
     */
    public function update(User $user, Quote $quote): bool
    {
        // Admin can update all quotes
        if ($user->isAdmin()) {
            return true;
        }

        // Sales users can only update their own quotes
        return $user->id === $quote->user_id;
    }

    /**
     * Determine if the user can delete the quote.
     * 
     * Sales users can only delete their own quotes.
     * Admin users can delete all quotes.
     */
    public function delete(User $user, Quote $quote): bool
    {
        // Admin can delete all quotes
        if ($user->isAdmin()) {
            return true;
        }

        // Sales users can only delete their own quotes
        return $user->id === $quote->user_id;
    }
}
