// Diarium stores local wall time as .NET ticks (100 ns since 0001-01-01).
// Keep the 64-bit arithmetic in SQLite to preserve all seven fractional digits.
export const diaryQuery = `
SELECT strftime('%Y-%m-%dT%H:%M:%S', e.DiaryEntryId / 10000000 - 62135596800, 'unixepoch')
         || printf('.%07d', e.DiaryEntryId % 10000000),
       e.Heading, e.Text, e.Rating, e.Latitude, e.Longitude,
       (SELECT json_group_array(value) FROM (
           SELECT t.Value AS value FROM EntryTags et JOIN Tags t USING (DiaryTagId)
           WHERE et.DiaryEntryId = e.DiaryEntryId AND t.Type = 0
           ORDER BY et.rowid
       ))
FROM Entries e ORDER BY e.DiaryEntryId`
