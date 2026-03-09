-- Migration: Create whatsapp_log table
-- Used by: Feature 3 – WhatsApp Message History Log
CREATE TABLE IF NOT EXISTS whatsapp_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NULL,
    contact_id INT NULL,
    message_type VARCHAR(50) NOT NULL DEFAULT 'custom',
    message_content TEXT NOT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_by VARCHAR(100) NOT NULL DEFAULT 'system',
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    INDEX idx_booking_id (booking_id),
    INDEX idx_contact_id (contact_id),
    INDEX idx_sent_at (sent_at)
);
