FROM node:26.7-slim

RUN apt update && apt install -y watch
RUN npm install -g sass typescript@5.8.2 terser

COPY ./builder.sh /builder.sh
RUN chmod +x /builder.sh
ENV TERM xterm

WORKDIR /app

ENTRYPOINT /builder.sh
