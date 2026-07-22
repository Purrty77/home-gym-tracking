USE muscu;

-- Rename the existing exercise in place so its workout history, statistics,
-- records and achievements remain attached to the same exercise ID.
UPDATE exercises
SET name='Rear Delt Fly (Alternate Machine)'
WHERE name='Rear Delt Fly';
