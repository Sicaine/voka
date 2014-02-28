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

class { ['apache::mod::php', 'apache::mod::rewrite']: }

apache::vhost { 'dashi' :
   port => '80', 
   docroot => '/var/www/app/web',
   docroot_owner => 'www-data',
   docroot_group => 'www-data',
   override => ['All'],
}

class { 'php' :
    service => 'apache2',
    require => Class['apache::mod::php']
}

php::augeas { 'php-date_timezone':
  entry  => 'Date/date.timezone',
  value  => 'Europe/Minsk',
  require => Class['php']
}

php::augeas { 'php-short_open_tag':
  entry => 'PHP/short_open_tag',
  value => 'Off',
  require => Class['php']
}

package {'php5-mysql': ensure => present }
package {'php-apc': ensure => present }

class { '::mysql::server':
  root_password    => 'root',
  databases => { 'dashi' => {
      ensure => 'present',
      charset => 'utf8',
    }
  },
}
