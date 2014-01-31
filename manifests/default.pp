include '::mysql::server'

Exec["apt-get update"] -> Package <| |>

file { '/var/www/app':
  ensure => directory,
  recurse => true
}

exec { "apt-get update":
  path => "/usr/bin",
}

class { 'apache' : }
apache::vhost { 'dashi' :
   port => '80', 
   docroot => '/var/www/app/web',
   docroot_owner => 'www-data',
   docroot_group => 'www-data'
}

class { 'php' :
    service => 'apache2',
    require => Service['apache']
}

package {'php5-mysql': ensure => present }
