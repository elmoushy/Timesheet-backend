# Memory optimization for image handling
# Add these settings to your php.ini or in a .htaccess file

# Increase memory limit for image processing
memory_limit = 512M

# Increase max file size for uploads
upload_max_filesize = 10M
post_max_size = 12M

# Increase execution time for image processing
max_execution_time = 300
max_input_time = 300

# Optimize garbage collection
zend.enable_gc = 1
gc_probability = 1
gc_divisor = 100

# Optimize realpath cache
realpath_cache_size = 4096K
realpath_cache_ttl = 600

# Optimize opcache for better performance
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.validate_timestamps = 1
