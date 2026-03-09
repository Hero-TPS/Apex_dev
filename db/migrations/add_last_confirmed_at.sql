-- Migration: Add last_confirmed_at column to bookings table
-- Used by: Feature 1 – Tomorrow's Booking Confirmations Widget
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS last_confirmed_at DATETIME NULL DEFAULT NULL;
