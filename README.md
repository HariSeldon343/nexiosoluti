# NexioSoluti

## Collabora Installer

Use `install_collabora.bat` to load the Collabora database schema.

```
install_collabora.bat <path-to-sql-dump> [mysql_password]
```

* Provide the SQL dump as the first argument.
* Optionally pass the MySQL password as the second argument. When supplied, the
  script exports the value to `MYSQL_PWD` and runs `mysql` with
  `--password=%MYSQL_PWD%` so the import remains fully unattended.
* If the password argument is omitted, the script falls back to prompting for
  the password interactively. Press **Enter** to leave it blank.
* You can also predefine the `MYSQL_PWD` environment variable to avoid both the
  argument and the prompt.

The script defaults to connecting to `localhost` with the `root` user and the
`collabora` database. Override these defaults by setting the `MYSQL_HOST`,
`MYSQL_USER`, or `MYSQL_DATABASE` environment variables before running the
installer.
