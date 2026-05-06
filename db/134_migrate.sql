-- Add standalone index on identifier for mod ID search lookups.
-- The existing UNIQUE(modId, identifier, version) index cannot be used
-- for queries that filter only by identifier.
CREATE INDEX `idx_identifier` ON `modReleases` (`identifier`);
