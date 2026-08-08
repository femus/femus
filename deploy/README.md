# Autonomous node (systemd)

Run a femus script at boot on a headless Linux board (Raspberry Pi, Orange Pi…),
so the node comes up on its own: plug in power, and a few seconds later it's working —
no keyboard, no monitor, no login.

## Install

```bash
# 1. clone + install where the service expects it
git clone https://github.com/femus/femus.git ~/femus
cd ~/femus && composer install --no-dev

# 2. (optional) pin the serial port instead of autodetect
echo 'FEMUS_PORT=/dev/ttyUSB0' | sudo tee /etc/femus-node.env

# 3. install and enable the service
sudo cp deploy/femus-node.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now femus-node
```

## Manage

```bash
systemctl status femus-node       # is it running?
journalctl -u femus-node -f       # live logs (heartbeat prints here)
sudo systemctl restart femus-node # restart
sudo systemctl disable --now femus-node
```

## Customize

The unit runs `examples/heartbeat.php` by default — swap `ExecStart` for your own
script (a radio node, a sensor logger, the messenger bridge…). `Restart=always`
brings it back after a crash or a board replug; `EnvironmentFile` keeps the port out
of the unit file.

The user must be in the `dialout` group to access the serial port:

```bash
sudo usermod -aG dialout femus   # then re-login or reboot
```
