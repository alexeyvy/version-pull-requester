VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|

    config.vm.box = "debian/stretch64"

    #use the same insecure key for all vms
    config.ssh.insert_key = false

    config.vm.define "master" do |master|
            master.vm.network :private_network, ip: "192.168.52.3"
        master.vm.provider :virtualbox do |vb|
          vb.memory = 2048
          vb.name = "github"
        end
    end

#    config.vm.synced_folder ".", "/vagrant", type: "nfs", mount_options: ['rw', 'vers=3', 'tcp', 'fsc']
    config.vm.synced_folder ".", "/vagrant", type: "nfs"
    config.nfs.map_uid = Process.uid
    config.nfs.map_gid = Process.gid
#    config.vm.synced_folder "./", "/vagrant", mount_options: ["dmode=777,fmode=777"]
end

