<?php
// Fetch all contacts
function getAllContacts(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, name, phone, email, address, additional_info FROM contacts ORDER BY name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch one contact by ID
function getContactById(PDO $pdo, int $id): ?array
{
    if ($id <= 0) return null;
    $stmt = $pdo->prepare("SELECT id, name, phone, email, address, additional_info FROM contacts WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// Update contact
function updateContact(PDO $pdo, int $id, array $data): bool
{
    $sql = "UPDATE contacts SET name = ?, phone = ?, email = ?, address = ?, additional_info = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['name'],
        $data['phone'],
        $data['email'],
        $data['address'],
        $data['additional_info'],
        $id
    ]);
}

// Delete contact
function deleteContact(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}
