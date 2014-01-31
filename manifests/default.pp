include '::mysql::server'

Exec["apt-get update"] -> Package <| |>

file { '/var/www/app':
  ensure => directory,
  recurse => true
}

exec { "apt-get update":
  path => "/usr/bin",
}

class { 'apache' :
  mpm_module => prefork
}

class { 'apache::mod::php':
}

apache::vhost { 'dashi' :
   port => '80', 
   docroot => '/var/www/app/web',
   docroot_owner => 'www-data',
   docroot_group => 'www-data'
}

class { 'php' :
    service => 'apache2',
    require => Class['apache::mod::php']
}

package {'php5-mysql': ensure => present }
