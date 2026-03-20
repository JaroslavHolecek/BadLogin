# Config
Config is in file bl_config.php
Read config values via bl_config_get() for reasonable exception checking
 - keys with _ prefix have value check

# Exceptions
    ## Bl_Exception
        ### Code
        - Bl_Exception::USER_ERROR === 1
        - Error of user input or action. Creating already existing account etc. 
        Bl_Exception::CONFIG_ERROR === 2 : Error in configuration file. Missing value etc.
        Bl_Exception::SYSTEM_ERROR === 4 : Internal error. Failed connection to database etc.

# No unnecessary bullying 
- If you want to have short/weak password, you can. Check password in your app if you want so. Preferring education over forcing.
