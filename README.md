*** pfSense SpeedTest Widget ***

<img width="568" height="204" alt="image" src="https://github.com/user-attachments/assets/8293bd59-7f7d-40e7-afb1-ce70a84bbcc2" />

This widget measures WAN connection performance using the speedtest-go package by showwin. It can automatically identify and use the most suitable Speedtest server based on network conditions, or it can be configured to run tests against a specific server selected by the user. This flexibility ensures both accurate speed measurements and reproducible test results when required. This widget measures WAN connection performance using the speedtest-go package. It can automatically identify and use the most suitable Speedtest server based on network conditions, or it can be configured to run tests against a specific server selected by the user. This flexibility ensures both accurate speed measurements and reproducible test results when required.

Based on speedtest-go utility by showwin (https://github.com/showwin/speedtest-go) and pfsense-speedtest-widget widget by LeonStraathof (https://github.com/LeonStraathof/pfsense-speedtest-widget)


INSTALL

    Diagnotics -> Command Prompt -> Execute Shell Command:
        pkg update
    Diagnotics -> Command Prompt -> Execute Shell Command:
        pkg install -y speedtest-go
    Diagnotics -> Command Prompt -> Upload File:
        speedtest.widget.php
    Diagnotics -> Command Prompt -> Execute Shell Command:
        mv -f /tmp/speedtest.widget.php /usr/local/www/widgets/widgets/
    Status -> Dashboard:
        Add the speedtest widget


UNINSTALL

    Status -> Dashboard:
        Remove the speedtest widget.
    Diagnotics -> Command Prompt -> Execute Shell Command:
        rm -f /usr/local/www/widgets/widgets/speedtest.widget.php
    Diagnotics -> Command Prompt -> Execute Shell Command:
        pkg remove -y speedtest-go
