<template>
  <div class="mt-12">
    <div class="text-center ma-2">
      <v-snackbar v-model="snackbar" top="top" color="secondary" elevation="24">
        {{ response }}
      </v-snackbar>
    </div>
    <v-dialog v-model="qualification_info" max-width="700px">
      <v-card>
        <v-card-actions>
          <span class="headline">Qualification Info</span>
          <v-spacer></v-spacer>
        </v-card-actions>
        <v-card-text>
          <v-container>
            <v-row>
              <v-col cols="6">
                <div class="form-group">
                  <label class="col-form-label">{{
                    caps("certificate")
                  }}</label>
                  <input
                    v-model="qualification_list.certificate"
                    type="text"
                    class="form-control"
                  />
                  <span
                    v-if="errors && errors.certificate"
                    class="text-danger mt-2"
                    >{{ errors.certificate[0] }}</span
                  >
                </div>
              </v-col>
              <v-col cols="6">
                <div class="form-group">
                  <label class="col-form-label">{{ caps("collage") }}</label>
                  <input
                    v-model="qualification_list.collage"
                    type="text"
                    class="form-control"
                  />
                  <span
                    v-if="errors && errors.collage"
                    class="text-danger mt-2"
                    >{{ errors.collage[0] }}</span
                  >
                </div>
              </v-col>
              <v-col cols="6">
                <div class="form-group">
                  <label class="col-form-label">{{ caps("start date") }}</label>
                  <input
                    v-model="qualification_list.start"
                    class="form-control"
                    type="date"
                  />
                  <span
                    v-if="errors && errors.start"
                    class="text-danger mt-2"
                    >{{ errors.start[0] }}</span
                  >
                </div>
              </v-col>
              <v-col cols="6">
                <div class="form-group">
                  <label class="col-form-label">{{ caps("end date") }}</label>
                  <input
                    v-model="qualification_list.end"
                    type="date"
                    class="form-control"
                  />
                  <span v-if="errors && errors.end" class="text-danger mt-2">{{
                    errors.end[0]
                  }}</span>
                </div>
              </v-col>
              <v-col cols="6">
                <div class="form-group">
                  <label class="col-form-label">{{ caps("type") }}</label>
                  <input
                    v-model="qualification_list.type"
                    type="text"
                    class="form-control"
                  />
                  <span v-if="errors && errors.type" class="text-danger mt-2">{{
                    errors.type[0]
                  }}</span>
                </div>
              </v-col>
              <span v-if="errors && errors.length" class="error--text">{{
                errors
              }}</span>
            </v-row>
          </v-container>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn class="error" small @click="close_qualification_info">
            Cancel
          </v-btn>
          <v-btn class="primary" small @click="save_qualification_info"
            >Save</v-btn
          >
        </v-card-actions>
      </v-card>
    </v-dialog>
    <table>
      <tr>
        <th></th>
        <td style="text-align: right;">
          <v-icon
            v-if="can(`employee_personal_edit_access`)"
            @click="qualification_info = true"
            small
            class="grey"
            style="border-radius: 50%; padding: 5px"
            color="secondary"
            >mdi-pencil</v-icon
          >
        </td>
      </tr>
      <tr>
        <th>Certificate</th>
        <td>
          {{ caps(qualification_list.certificate) }}
        </td>
      </tr>

      <tr>
        <th>Collage</th>
        <td>
          {{ caps(qualification_list.collage) }}
        </td>
      </tr>

      <tr>
        <th>Start</th>
        <td>
          {{ caps(qualification_list.start) }}
        </td>
      </tr>

      <tr>
        <th>End</th>
        <td>
          {{ caps(qualification_list.end) }}
        </td>
      </tr>

      <tr>
        <th>Type</th>
        <td>
          {{ caps(qualification_list.type) }}
        </td>
      </tr>
    </table>
  </div>
</template>

<script>
export default {
  props: ["qualification_list"],
  data() {
    return {
      qualification_info: false,
      snackbar: false,
      response: "",
      errors: []
    };
  },
  methods: {
    caps(str) {
      if (str == "" || str == null) {
        return "---";
      } else {
        let res = str.toString();
        return res.replace(/\b\w/g, c => c.toUpperCase());
      }
    },
    can(item) {
      return true;
    },

    save_qualification_info() {
      let payload = {
        ...this.qualification_list,
        company_id: this.$auth?.user?.company?.id,
        employee_id: this.qualification_list.employee_id
      };

      this.$axios
        .post(`qualification`, payload)
        .then(({ data }) => {
          this.loading = false;

          if (!data.status) {
            this.errors = data.errors;
          } else {
            this.errors = [];
            this.snackbar = true;
            this.response = data.message;
            this.close_qualification_info();
          }
        })
        .catch(e => console.log(e));
    },

    close_qualification_info() {
      this.qualification_info = false;
      this.errors = [];
      setTimeout(() => {}, 300);
    }
  }
};
</script>

<style scoped>
table {
  font-family: arial, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

td,
th {
  text-align: left;
  padding: 8px;
}

tr:nth-child(even) {
  background-color: #fbfdff;
}
</style>
