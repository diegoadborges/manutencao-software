Vagrant.configure("2") do |config|
  (1..2).each do |i|
    config.vm.define "vm#{i}" do |vm_config|
      vm_config.vm.box = "ubuntu/focal64"
      vm_config.vm.hostname = "vm#{i}"
      vm_config.vm.network "private_network", type: "dhcp"
      vm_config.vm.provider "virtualbox" do |vb|
        vb.memory = 1024
        vb.cpus = 1
      end
      vm_config.vm.provision "shell", inline: <<-SHELL
        sudo apt update
      SHELL
    end
  end
end

