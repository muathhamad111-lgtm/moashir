SELECT 'CREATE DATABASE moashir_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'moashir_testing')\gexec
