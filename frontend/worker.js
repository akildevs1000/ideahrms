const WebSocket = require("ws");
require("dotenv").config();
const axios = require("axios").default;

const fs = require("fs");
let socket = new WebSocket(process.env.SOCKET_ENDPOINT);
socket.onopen = () => console.log("connected\n");

socket.onmessage = ({ data }) => {
  console.log("francis")
  let {
    UserCode: UserID,
    DeviceID,
    RecordDate: LogTime,
    RecordNumber: SerialNumber,
  } = JSON.parse(data).Data;

  if (UserID !== 0) {
    let str = `${UserID},${DeviceID},${LogTime},${SerialNumber}`;
    fs.appendFileSync("logs.csv", str + "\n");

    let payload = {
      UserID,
      DeviceID,
      LogTime,
      SerialNumber,
      Hash: process.env.Hash,
    };
    console.log(str);

    axios.post(process.env.APP_URL, payload).then(({ data }) => {
      console.table([data]);
    });
  }
};