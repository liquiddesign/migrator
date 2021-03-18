# ⇄ Migrátor
Migrátor je nástroj pro synchronizaci StORM Entit and SQL databáze

![Travis](https://travis-ci.org/liquiddesign/migrator.svg?branch=master)
![Release](https://img.shields.io/github/v/release/liquiddesign/migrator.svg?1)

## Dokumentace
☞ [Dropbox paper](https://paper.dropbox.com/doc/Migrator--A61fiZxTLsIh5pJTlZ5vgzEXAg-I9c1x2XfhJrbHQnKjwP2x)

## TODO
- update values befera CHANGE null -> not null = 
UPDATE `xx` SET `yyy`=DEF VALUE  WHERE `yyy` IS NULL;