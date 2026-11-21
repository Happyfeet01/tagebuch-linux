import assert from 'node:assert/strict'
import { test } from 'node:test'
import initSqlJs from 'sql.js/dist/sql-asm.js'
import { diaryQuery } from '../../src/diaryQuery.ts'

test('reads backup entries, keeps tick precision and associates only regular tags', async () => {
	const db = new (await initSqlJs()).Database()
	try {
		db.run(`
			CREATE TABLE Entries(DiaryEntryId INTEGER, Heading TEXT, Text TEXT, Rating INTEGER, Latitude REAL, Longitude REAL);
			CREATE TABLE EntryTags(DiaryEntryId INTEGER, DiaryTagId INTEGER);
			CREATE TABLE Tags(DiaryTagId INTEGER, Value TEXT, Type INTEGER);
			INSERT INTO Entries VALUES (621355968001234567,'Titel','<p>Test</p>',4,12,34),
				(621355968001234568,'Zwei','<p>Zwei</p>',0,0,0);
			INSERT INTO Tags VALUES(1,'Wandern',0),(2,'Person',1);
			INSERT INTO EntryTags VALUES(621355968001234567,1),(621355968001234567,2);
		`)
		const [first, second] = db.exec(diaryQuery)[0].values
		assert.deepEqual(first, ['1970-01-01T00:00:00.1234567','Titel','<p>Test</p>',4,12,34,'["Wandern"]'])
		assert.equal(second[0], '1970-01-01T00:00:00.1234568')
		assert.equal(second[6], '[]')
	} finally { db.close() }
})
