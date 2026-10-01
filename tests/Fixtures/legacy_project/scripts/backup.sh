#!/bin/bash
# legacy backup script
echo "Backing up database..."
mysqldump -u root -p database_name > backup.sql
