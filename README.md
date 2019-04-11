Evil Wizard Creations Multi Connection MySQL Database Handler
=========================

PDO MySQL Database Multi Connection, Master - Slave Handler System.

Features
--------

* Requires the definition of **APP\_ROOT** for the default config access to be used
* Config file support
* Multi DSN connection aliasing
* Ability to retry the Query for connection timeout with automatic reconnect
* Keep connection alive
* Wrapper to index PDOStatement results by a field value
* PSR-4 autoloading compliant structure
* Comprehensive Guides and tutorial
* Easy to use to any framework or even a plain php file

Configuration
--------

The main section of the config.

- **default_connection** - *The default connection alias to use if none specified.*
- **connections** - *A collection of database connection alias names and connection specific config.*

The connections sections are grouped with the database connection being aliased 
as the config key name and requires the following.

- **env** - *The connection environment.*
- **dsn** - *The connection data source name to connect to.*
- **database** - *The database name the connection is for.*
- **username** - *The database username to use for the connection.*
- **password** - *The password to use in conjunction with the username.*
- **port** - *Optional database connection port number, defaults to 3306.*
- **use\_mamp\_sockets** - *Optional flag to indicate the MAMP socket config should be added to the PDO connection.*

#### Example YAML Config Content


    default_connection: main
    connections:
        main:
            env: local
            dsn: localhost
            database: database
            username: username
            password: password
            port: 3306
            use_mamp_sockets: false


ToDo
--------

- [ ] **Manager** - *Support multiple username connections on the same host in addConnectionFromConfig().*
- [ ] **Manager** - *Check alias name for \_slave to use replication slaves and separate the configs and connections in makeNewConnection().*
- [ ] **Manager** - *Set the default connection as master on instance creation.*
- [ ] **Connection** - *Log SQL failure and check if it is a MySQL Server has gone away error and needs to reconnect in query().*
- [ ] **Connection** - *Add the DB aliases for the connection and PDO connections attributes in \_\_toString().*
- [ ] **Manager** - *Incorporate the use of the env setting.*

