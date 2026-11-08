-- migrations/20261108_1440_bookings_payment_method_card.sql
-- Adds 'card' as a booking payment method (card payments go into the same bank account as EFT).
-- Existing bookings keep 'cash' / 'eft'; the default stays 'cash'.
-- Run this BEFORE deploying the code that offers Card, otherwise saving a Card booking fails.
-- Safe to run more than once.
ALTER TABLE bookings
    MODIFY COLUMN payment_method ENUM('cash','eft','card') NOT NULL DEFAULT 'cash';
