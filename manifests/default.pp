include '::mysql::server'

file { '/var/www/app':
  ensure => directory,
  recurse => true
}

class { 'apache' : }
apache::vhost { 'dashi' :
   port => '80', 
   docroot => '/var/www/app/web',
}

class { 'php' : }
package {'php5-mysql': ensure => present }
