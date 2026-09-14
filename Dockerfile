FROM apache/apisix:3.18.0-ubuntu

ENV APISIX_STAND_ALONE true
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1

COPY ./conf/apisix/config.yaml /usr/local/apisix/conf/config.yaml
COPY ./conf/apisix/apisix.yaml /usr/local/apisix/conf/apisix.yaml
